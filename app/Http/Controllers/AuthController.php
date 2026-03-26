<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use App\Helpers\ResponseHelper;
use App\Mail\EmailOtpMail;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = JWTAuth::user();

        $userRole = \App\Models\UserRole::with(['role', 'organization'])
            ->where('user_id', $user->id)
            ->first();

        if (!$userRole) {
            return response()->json(['error' => 'User role not assigned'], 403);
        }

        $organization = $userRole->organization;

        // If parent exists use parent_id otherwise use organization id
        $organizationId = $organization->parent_id ?? $organization->id;

        // Make role human friendly
        $formattedRole = ucwords(str_replace('_', ' ', $userRole->role->name));

        $organizationName = $organization->name;
        $plainRefreshToken = Str::random(64);

        \DB::table('refresh_tokens')->insert([
            'user_id' => $user->id,
            'token' => Hash::make($plainRefreshToken),
            'expires_at' => Carbon::now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'message' => $message,
            'access_token' => $token,
            'refresh_token' => $plainRefreshToken,
            'token_type' => 'bearer',
            'organization_id' => $organizationId,
            'organization_name' => $organizationName,
            'user_role' => $userRole->role->name,
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }
    public function me()
    {
        $user = JWTAuth::user();
        return $user->load('userRoles.organization');
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Logged out']);
    }

    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string'
        ]);

        $tokenRecord = \DB::table('refresh_tokens')
            ->where('revoked', false)
            ->get()
            ->first(function ($record) use ($request) {
                return \Hash::check($request->refresh_token, $record->token);
            });

        if (!$tokenRecord) {
            return response()->json(['error' => 'Invalid refresh token'], 401);
        }

        if (\Carbon\Carbon::parse($tokenRecord->expires_at)->isPast()) {
            return response()->json(['error' => 'Refresh token expired'], 401);
        }

        $user = \App\Models\User::find($tokenRecord->user_id);

        $newAccessToken = JWTAuth::fromUser($user);

        $newRefreshToken = \Str::random(64);

        \DB::table('refresh_tokens')
            ->where('id', $tokenRecord->id)
            ->update([
                'token' => \Hash::make($newRefreshToken),
                'expires_at' => now()->addDays(14),
                'updated_at' => now(),
            ]);

        return response()->json([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ]);
    }
    public function register(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6',
            ],
            [
                'email.unique' => 'This email is already registered.'
            ]
        );
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 200);
        }

        DB::beginTransaction();

        try {

            // Get Default Organization
            $organization = Organization::where('name', 'Independent Artist')->first();

            if (!$organization) {
                throw new \Exception('Organization not found');
            }

            // Create User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            // Default Role
            $role = Role::where('name', 'standard_owner')->first();

            if (!$role) {
                throw new \Exception('role not found');
            }

            // Assign User Role
            UserRole::create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'role_id' => $role->id,
            ]);

            DB::commit();

            // Generate JWT Token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'role_name' => $role->name,
                'organization_parent_id' => $organization->parent_id
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // send otp
    public function sendOtp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email'    => 'required'
            ]);
            $email = $request->input('email');
            $user = User::where('email', '=', $email)->first();
            if ($user == null) {
                return ResponseHelper::Out('failed', 'unauthorized', null, 401);
            }
            $userName = $user->name;
            $otp = rand(100000, 999999);
            Mail::to($email)->send(new EmailOtpMail($otp, $userName));
            $user->update([
                'otp' => $otp,
                'email_verified_at' => Carbon::now()->addMinutes(10),
                'updated_by'     => $user->id,
            ]);
            return ResponseHelper::Out('success', 'OTP sent to your registered mail', ['otp' => $otp, 'user' => $user], 200);
        } catch (Exception $e) {
            return ResponseHelper::Out('failed', 'Something went wrong', $e->getMessage(), 500);
        }
    }


    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'otp' => 'required|min:6'
            ]);
            $email = $request->input('email');
            $otp = $request->input('otp');
            $user = User::where('email', '=', $email)->where('otp', '=', $otp)->first();
            if ($user == null) {
                return ResponseHelper::Out('failed', 'unauthorized', null, 401);
            }
            $user->update(['otp' => 0, 'otp_verified' => true, 'updated_by'     => $user->id,]);
            return ResponseHelper::Out('success', 'Otp verification successful!', $user, 200);
        } catch (ValidationException $e) {
            return ResponseHelper::Out('error', 'Validation Failed', $e->errors(), 422);
        } catch (Exception $e) {
            return ResponseHelper::Out('failed', 'Something went wrong', $e->getMessage(), 500);
        }
    }
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string|min:6|confirmed',
            ]);
            $email = $request->input('email');
            $user = User::where('email', $request->email)
                    ->where('otp_verified', true)
                    ->first();
            if (!$user) {
                if ($user == null) {
                    return ResponseHelper::Out('failed', 'unauthorized', null, 401);
                }
            }                
            //update password
            $user->update([
                'password' => Hash::make($request->input('password')),
                'otp_verified' => false,
                'updated_by'     => $user->id,
            ]);
            return ResponseHelper::Out('success', 'Password set successful!', $user, 200);
        } catch (ValidationException $e) {
            return ResponseHelper::Out('error', 'Validation Failed', $e->errors(), 422);
        } catch (Exception $e) {
            return ResponseHelper::Out('failed', 'Something went wrong', $e->getMessage(), 500);
        }
    }

}
