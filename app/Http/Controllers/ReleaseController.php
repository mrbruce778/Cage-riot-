<?php

namespace App\Http\Controllers;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Asset;
use App\Models\User;
use App\Models\Contributor;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;


class ReleaseController extends Controller
{
    private function generateUPC()
    {
        return '99' . str_pad(random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
    }
    private function getAllowedOrgIds($user)
    {
        $org = Organization::findOrFail($user->currentOrganizationId());

        // Always use parent if exists, otherwise self
        $mainOrgId = $org->parent_id ?? $org->id;

        return [$mainOrgId];
    }
    private function canManageRelease($user)
    {
        $orgId = $user->currentOrganizationId();

        return $user->hasRoleInOrganization('standard_owner', $orgId)
            || $user->hasRoleInOrganization('artist_owner', $orgId);
    }
    private function handleArtworkUpload(Request $request, $release, $user)
    {
        $file = $request->file('artwork');

        $path = $file->store('artworks', 'public');

        return Asset::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $user->currentOrganizationId(),
            'release_id' => $release->id,
            'asset_type' => 'artwork',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'created_by' => $user->id,
        ]);
    }
    public function index()
    {
        $user = Auth::user();
        $allowedOrgIds = $this->getAllowedOrgIds($user);

        $releases = Release::whereIn('organization_id', $allowedOrgIds)
            ->with(['artwork',
                    'creator:id,name',
                    'contributors'

            ])
            ->latest()
            ->get();
        $releases->transform(function ($release) {

            if ($release->artwork && $release->artwork->file_path) {
                if(str_starts_with($release->artwork->file_path,'releases/artwork/'))
                    {
                        $release->artwork->file_path = Storage::disk('s3')->temporaryUrl($release->artwork->file_path,
                    now()->addMinutes(10));
                    }else{
                $release->artwork->file_path = $release->artwork->file_path;
            }
                // $release->artwork->url = Storage::disk('s3')->temporaryUrl(
                //     $release->artwork->file_path,
                //     now()->addMinutes(15)
                // );
            }

            return $release;
        });
        return response()->json($releases);
    }
    // public function oldstore(Request $request)
    // {
    //     $user = Auth::user();

    //     // 🔐 Permission check
    //     if (!$this->canManageRelease($user)) {
    //         return response()->json(['error' => 'Forbidden'], 403);
    //     }

    //     // 🧠 Decode metadata if string
    //     if ($request->has('metadata')) {
    //         $request->merge([
    //             'metadata' => json_decode($request->metadata, true)
    //         ]);
    //     }

    //     // ✅ Validation
    //     $validated = $request->validate([
    //         'title' => 'required|string|max:255',
    //         'version_title' => 'nullable|string|max:255',
    //         'primary_artist_name' => 'required|string|max:255',
    //         'release_type' => 'required|string|max:50',
    //         'upc' => 'nullable|string|max:50',
    //         'label_name' => 'nullable|string|max:255',
    //         'release_date' => 'nullable|date',
    //         'original_release_date' => 'nullable|date',
    //         'metadata' => 'nullable|array',

    //         // ✅ NEW: artwork upload like track
    //         'artwork' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
    //     ]);

    //     // 🧠 Normalize organization
    //     $orgId = $user->currentOrganizationId();
    //     $userOrg = Organization::findOrFail($orgId);
    //     $organizationId = $userOrg->parent_id ?? $userOrg->id;

    //     DB::beginTransaction();

    //     try {

    //         $releaseData = collect($validated)->except(['artwork'])->toArray();

    //         // 🧱 Create release
    //         $release = Release::create([
    //             ...$releaseData,
    //             'organization_id' => $organizationId,
    //             'created_by' => $user->id,
    //             'status' => 'draft',
    //         ]);

    //         // 🖼️ Artwork upload (LIKE TRACK API)
    //         if ($request->hasFile('artwork')) {

    //             $file = $request->file('artwork');
    //             $path = $file->store('releases/artwork', 'public');

    //             $asset = Asset::create([
    //                 'organization_id' => $organizationId,
    //                 'release_id' => $release->id,
    //                 'asset_type' => 'artwork',
    //                 'file_name' => $file->getClientOriginalName(),
    //                 'file_path' => $path,
    //                 'mime_type' => $file->getMimeType(),
    //                 'file_size' => $file->getSize(),
    //                 'created_by' => $user->id,
    //             ]);

    //             // 🔗 Attach artwork to release
    //             $release->update([
    //                 'artwork_asset_id' => $asset->id
    //             ]);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Release created successfully',
    //             'data' => $release->load('artwork')
    //         ], 201);

    //     } catch (\Exception $e) {

    //         DB::rollBack();

    //         return response()->json([
    //             'error' => 'Failed to create release',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // 🎯 Allowed roles
        $allowedRoles = [
            'primary_artist',
            'featuring_artist',
            'with_artist',
            'remixer',

            'performer',
            'lead_vocalist',
            'background_vocalist',
            'vocalist',
            'rapper',
            'singer',
            'instrumentalist',
            'guitarist',
            'drummer',
            'bassist',
            'keyboardist',
            'pianist',
            'dj',

            'producer',
            'co_producer',
            'executive_producer',
            'composer',
            'songwriter',
            'lyricist',
            'arranger',
            'programmer',
            'beat_maker',
            'sound_designer',

            'engineer',
            'recording_engineer',
            'mixing_engineer',
            'mastering_engineer',
            'assistant_engineer',
            'audio_editor',
        ];

        // ✅ Validation
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'version_title' => 'nullable|string|max:255',
            'primary_artist_name' => 'required|string|max:255',
            'release_type' => 'required|string|max:50',
            'label_name' => 'nullable|string|max:255',

            // 🎯 UPC
            'upc' => [
                'nullable',
                'string',
                'size:12',
                Rule::unique('releases', 'upc')
            ],
            'auto_generate_upc' => 'boolean',

            // 🎯 timing
            'previously_released' => 'boolean',
            'release_timing' => 'nullable|in:asap,date',
            'scheduled_release_date' => 'nullable|date',

            // 🎤 contributors
            'contributors' => 'required|array|min:1',
            'contributors.*.name' => 'required|string|max:255',
            'contributors.*.role' => 'required|string',
            'contributors.*.user_id' => 'required|exists:users,id',
            // 🎨 artwork
            'file_path' => 'nullable|string',
            'file_name' => 'nullable|string',
            'mime_type' => 'nullable|string',
            'file_size' => 'nullable|integer',
            'primary_genre' => 'nullable|string',
            'secondary_genre' => 'nullable|string',
        ]);

        // 🧠 Organization normalization
        $orgId = $user->currentOrganizationId();
        $userOrg = Organization::findOrFail($orgId);
        $organizationId = $userOrg->parent_id ?? $userOrg->id;

        DB::beginTransaction();

        try {

            // 🚀 UPC LOGIC
            if ($request->auto_generate_upc) {

                do {
                    $upc = $this->generateUPC();
                } while (Release::where('upc', $upc)->exists());

                $validated['upc'] = $upc;

            } else {

                if (empty($validated['upc'])) {
                    throw new \Exception("UPC is required or enable auto-generate");
                }
            }

            // 🚀 RELEASE TIMING LOGIC
            $previouslyReleased = $request->boolean('previously_released');
            $releaseTiming = $request->input('release_timing');
            $scheduledDate = $request->input('scheduled_release_date');

            if ($previouslyReleased) {
                $validated['release_timing'] = null;
                $validated['scheduled_release_date'] = null;
            } else {

                if (!$releaseTiming) {
                    throw new \Exception("release_timing is required");
                }

                if ($releaseTiming === 'asap') {
                    $validated['scheduled_release_date'] = null;
                }

                if ($releaseTiming === 'date') {

                    if (!$scheduledDate) {
                        throw new \Exception("scheduled_release_date is required when release_timing is 'date'");
                    }

                    if (strtotime($scheduledDate) < strtotime(date('Y-m-d'))) {
                        throw new \Exception("scheduled_release_date cannot be in the past");
                    }

                    $validated['scheduled_release_date'] = $scheduledDate;
                }

                if (!in_array($releaseTiming, ['asap', 'date'])) {
                    throw new \Exception("Invalid release_timing value");
                }

                $validated['release_timing'] = $releaseTiming;
            }

            // 🎯 Prepare release data
            $releaseData = collect($validated)->except([
                'contributors',
                'file_path',
                'file_name',
                'mime_type',
                'file_size'
            ])->toArray();

            // 🧱 Create release
            $release = Release::create([
                ...$releaseData,
                'organization_id' => $organizationId,
                'created_by' => $user->id,
                'status' => 'draft',
            ]);

            // 🎤 HANDLE CONTRIBUTORS
            $primaryArtistCount = 0;

            foreach ($request->contributors as $item) {

                if (!in_array($item['role'], $allowedRoles)) {
                    throw new \Exception("Invalid role: " . $item['role']);
                }

            $contributor = Contributor::firstOrCreate([
                'user_id' => $item['user_id'],
                'organization_id' => $organizationId,
            ], [
                'name' => $item['name'],
            ]);

                if ($item['role'] === 'primary_artist') {
                    $primaryArtistCount++;

                }

                DB::table('release_contributors')->insert([
                    'id' => (string) Str::uuid(),
                    'release_id' => $release->id,
                    'contributor_id' => $contributor->id,
                    'role' => $item['role'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($primaryArtistCount !== 1) {
                throw new \Exception("Exactly one primary artist is required");
            }

            // 🖼 Artwork
            if ($request->filled('file_path')) {

                $asset = Asset::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organizationId,
                    'release_id' => $release->id,
                    'asset_type' => 'artwork',
                    'file_name' => $request->file_name,
                    'file_path' => $request->file_path,
                    'mime_type' => $request->mime_type,
                    'file_size' => $request->file_size,
                    'created_by' => $user->id,
                ]);

                $release->update([
                    'artwork_asset_id' => $asset->id
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Release created successfully',
                'data' => $release->load('contributors')
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Failed to create release',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function show($id)
    {
        $user = Auth::user();

        $allowedOrgIds = $this->getAllowedOrgIds($user);

        $release = Release::whereIn('organization_id', $allowedOrgIds)
            ->with(['tracks', 'artwork', 'creator:id,name'])
            ->findOrFail($id);

        // Apply same artwork logic
        if ($release->artwork && $release->artwork->file_path) {

            if (str_starts_with($release->artwork->file_path, 'releases/artwork/')) {

                $release->artwork->file_path = Storage::disk('s3')->temporaryUrl(
                    $release->artwork->file_path,
                    now()->addMinutes(10)
                );

            } else {
                $release->artwork->file_path = $release->artwork->file_path;
            }
        }

        return response()->json($release);
    }
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $allowedOrgIds = $this->getAllowedOrgIds($user);

        $release = Release::whereIn('organization_id', $allowedOrgIds)
            ->findOrFail($id);

        // 🎯 Allowed roles
        $allowedRoles = [
            'primary_artist','featuring_artist','with_artist','remixer',
            'performer','lead_vocalist','background_vocalist','vocalist',
            'rapper','singer','instrumentalist','guitarist','drummer',
            'bassist','keyboardist','pianist','dj',
            'producer','co_producer','executive_producer','composer',
            'songwriter','lyricist','arranger','programmer','beat_maker',
            'sound_designer',
            'engineer','recording_engineer','mixing_engineer',
            'mastering_engineer','assistant_engineer','audio_editor',
        ];

        /*
        |--------------------------------------------------------------------------
        | ✅ VALIDATION
        |--------------------------------------------------------------------------
        */
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'version_title' => 'nullable|string|max:255',
            'primary_artist_name' => 'nullable|string|max:255',
            'release_type' => 'nullable|string|max:50',
            'label_name' => 'nullable|string|max:255',

            // 🎯 UPC
            'upc' => [
                'nullable',
                'string',
                'size:12',
                Rule::unique('releases', 'upc')->ignore($release->id)
            ],
            'auto_generate_upc' => 'boolean',

            // 🎯 timing
            'previously_released' => 'boolean',
            'release_timing' => 'nullable|in:asap,date',
            'scheduled_release_date' => 'nullable|date',

            // 🎤 contributors (partial sync)
            'contributors' => 'nullable|array',
            'contributors.*.id' => 'nullable|uuid|exists:release_contributors,id',
            'contributors.*.name' => 'required|string|max:255',
            'contributors.*.role' => 'required|string',
            'contributors.*.user_id' => 'required|exists:users,id',

            'deleted_contributor_ids' => 'nullable|array',
            'deleted_contributor_ids.*' => 'uuid|exists:release_contributors,id',

            // 🎨 artwork
            'file_path' => 'nullable|string',
            'file_name' => 'required_with:file_path|string',
            'mime_type' => 'required_with:file_path|string',
            'file_size' => 'required_with:file_path|integer',

            // 📌 status
            'status' => 'nullable|in:draft,published,archived',
        ]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | 🚀 UPC LOGIC
            |--------------------------------------------------------------------------
            */
            if ($request->auto_generate_upc) {

                do {
                    $upc = $this->generateUPC();
                } while (
                    Release::where('upc', $upc)
                        ->where('id', '!=', $release->id)
                        ->exists()
                );

                $validated['upc'] = $upc;

            } else {
                if (array_key_exists('upc', $validated) && empty($validated['upc'])) {
                    throw new \Exception("UPC is required or enable auto-generate");
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 🚀 RELEASE TIMING LOGIC
            |--------------------------------------------------------------------------
            */
            if ($request->has('previously_released') || $request->has('release_timing')) {

                $previouslyReleased = $request->boolean('previously_released');
                $releaseTiming = $request->input('release_timing');
                $scheduledDate = $request->input('scheduled_release_date');

                if ($previouslyReleased) {

                    $validated['release_timing'] = null;
                    $validated['scheduled_release_date'] = null;

                } else {

                    if (!$releaseTiming) {
                        throw new \Exception("release_timing is required");
                    }

                    if ($releaseTiming === 'asap') {
                        $validated['scheduled_release_date'] = null;
                    }

                    if ($releaseTiming === 'date') {

                        if (!$scheduledDate) {
                            throw new \Exception("scheduled_release_date is required when release_timing is 'date'");
                        }

                        if (strtotime($scheduledDate) < strtotime(date('Y-m-d'))) {
                            throw new \Exception("scheduled_release_date cannot be in the past");
                        }

                        $validated['scheduled_release_date'] = $scheduledDate;
                    }

                    if (!in_array($releaseTiming, ['asap', 'date'])) {
                        throw new \Exception("Invalid release_timing value");
                    }

                    $validated['release_timing'] = $releaseTiming;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 🧱 UPDATE RELEASE
            |--------------------------------------------------------------------------
            */
            $releaseData = collect($validated)->except([
                'contributors',
                'deleted_contributor_ids',
                'file_path',
                'file_name',
                'mime_type',
                'file_size'
            ])->toArray();

            $release->update($releaseData);

            /*
            |--------------------------------------------------------------------------
            | ❌ DELETE CONTRIBUTORS (partial)
            |--------------------------------------------------------------------------
            */
            if (!empty($validated['deleted_contributor_ids'])) {
                DB::table('release_contributors')
                    ->where('release_id', $release->id)
                    ->whereIn('id', $validated['deleted_contributor_ids'])
                    ->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | ➕ UPDATE / INSERT CONTRIBUTORS
            |--------------------------------------------------------------------------
            */
            if (!empty($validated['contributors'])) {

                foreach ($validated['contributors'] as $item) {

                    if (!in_array($item['role'], $allowedRoles)) {
                        throw new \Exception("Invalid role: " . $item['role']);
                    }

                    $contributor = Contributor::firstOrCreate([
                        'user_id' => $item['user_id'],
                        'organization_id' => $release->organization_id,
                    ], [
                        'name' => $item['name'],
                    ]);

                    // ✅ UPDATE
                    if (!empty($item['id'])) {

                        DB::table('release_contributors')
                            ->where('id', $item['id'])
                            ->where('release_id', $release->id)
                            ->update([
                                'role' => $item['role'],
                                'updated_at' => now(),
                            ]);

                    } else {
                        // ✅ INSERT
                        DB::table('release_contributors')->insert([
                            'id' => (string) Str::uuid(),
                            'release_id' => $release->id,
                            'contributor_id' => $contributor->id,
                            'role' => $item['role'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 🔒 PRIMARY ARTIST VALIDATION (FINAL CHECK)
            |--------------------------------------------------------------------------
            */
            $primaryCount = DB::table('release_contributors')
                ->where('release_id', $release->id)
                ->where('role', 'primary_artist')
                ->count();

            if ($primaryCount !== 1) {
                throw new \Exception("Exactly one primary artist is required");
            }

            /*
            |--------------------------------------------------------------------------
            | 🖼 ARTWORK UPDATE
            |--------------------------------------------------------------------------
            */
            if ($request->filled('file_path')) {

                // optional: delete old artwork
                if ($release->artwork_asset_id) {
                    Asset::where('id', $release->artwork_asset_id)->delete();
                }

                $asset = Asset::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $release->organization_id,
                    'release_id' => $release->id,
                    'asset_type' => 'artwork',
                    'file_name' => $request->file_name,
                    'file_path' => $request->file_path,
                    'mime_type' => $request->mime_type,
                    'file_size' => $request->file_size,
                    'created_by' => $user->id,
                ]);

                $release->update([
                    'artwork_asset_id' => $asset->id
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Release updated successfully',
                'data' => $release->fresh()->load(['artwork', 'contributors'])
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Failed to update release',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function destroy($id)
    {
        $user = Auth::user();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $userOrg = Organization::findOrFail($user->currentOrganizationId());
        $normalizedOrgId = $userOrg->parent_id ?? $userOrg->id;

        $release = Release::where('organization_id', $normalizedOrgId)
            ->findOrFail($id);
        $release->tracks()->delete();
        $release->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
    public function getUsersByOrganization($orgId)
    {
        // Step 1: Resolve parent or self
        $userOrg = Organization::findOrFail($orgId);
        $organizationId = $userOrg->parent_id ?? $userOrg->id;

        // Step 2: Get users using scope
        $users = User::withArtistRoles($organizationId)
            ->with([
                'userRoles.role',
                'userRoles.organization'
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
}