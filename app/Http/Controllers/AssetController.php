<?php

namespace App\Http\Controllers;
use App\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use App\Models\Release;
use Illuminate\Support\Facades\Auth;

class AssetController extends Controller
{


    public function uploadArtwork(Request $request, Release $release)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $orgId = $user->currentOrganizationId();

        if (!$orgId) {
            return response()->json(['error' => 'Organization missing'], 400);
        }

        $userOrg = Organization::find($orgId);

        if (!$userOrg) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        $allowedOrgIds = array_filter([
            $userOrg->id,
            $userOrg->parent_id
        ]);

        if (!in_array($release->organization_id, $allowedOrgIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            // delete old artwork
            if ($release->artwork) {
                if (Storage::disk('public')->exists($release->artwork->file_path)) {
                    Storage::disk('public')->delete($release->artwork->file_path);
                }
                $release->artwork->delete();
            }

            $file = $request->file('file');
            $path = $file->store('artworks', 'public');

            $asset = Asset::create([
                'id' => (string) \Str::uuid(),
                'organization_id' => $userOrg->parent_id ?? $userOrg->id,
                'release_id' => $release->id,
                'asset_type' => 'artwork',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'created_by' => $user->id,
            ]);

            $release->update([
                'artwork_asset_id' => $asset->id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Artwork uploaded successfully',
                'data' => $release->fresh()->load('artwork')
            ]);

        } catch (\Exception $e) {

            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            DB::rollBack();

            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}