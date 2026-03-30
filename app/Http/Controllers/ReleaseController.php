<?php

namespace App\Http\Controllers;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;


class ReleaseController extends Controller
{
    private function getAllowedOrgIds($user)
    {
        $org = Organization::findOrFail($user->currentOrganizationId());

        return array_filter([
            $org->id,
            $org->parent_id
        ]);
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
            ->with('artwork')
            ->latest()
            ->get();

        return response()->json($releases);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // 🔐 Permission check
        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        if ($request->has('metadata')) {
            $request->merge([
            'metadata' => json_decode($request->metadata, true)
            ]       );
        }
        // ✅ Validation
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'version_title' => 'nullable|string|max:255',
            'primary_artist_name' => 'required|string|max:255',
            'release_type' => 'required|string|max:50',
            'upc' => 'nullable|string|max:50',
            'label_name' => 'nullable|string|max:255',
            'release_date' => 'nullable|date',
            'original_release_date' => 'nullable|date',
            'metadata' => 'nullable|array',
            'artwork' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // 🧠 Normalize organization (parent or self)
        $orgId = $user->currentOrganizationId();
        $userOrg = Organization::findOrFail($orgId);
        $organizationId = $userOrg->parent_id ?? $userOrg->id;

        DB::beginTransaction();

        try {
            // ❗ Remove artwork from release data
            $releaseData = collect($validated)->except('artwork')->toArray();

            // 🧱 Create release
            $release = Release::create([
                ...$releaseData,
                'organization_id' => $organizationId,
                'created_by' => $user->id,
                'status' => 'draft',
            ]);

            // 🖼 Upload artwork if exists
            if ($request->hasFile('artwork')) {

                $file = $request->file('artwork');
                $path = $file->store('artworks', 'public');

                $asset = Asset::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organizationId,
                    'release_id' => $release->id,
                    'asset_type' => 'artwork',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => $user->id,
                ]);

                // 🔗 Attach artwork
                $release->update([
                    'artwork_asset_id' => $asset->id
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Release created successfully',
                'data' => $release->load('artwork')
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
            ->with(['tracks', 'artwork'])
            ->findOrFail($id);
        return response()->json($release);
    }

    // public function update(Request $request, $id)
    // {
    //     $user = Auth::user();
    //     if (!$this->canManageRelease($user)) {
    //         return response()->json(['error' => 'Forbidden'], 403);
    //     }
    //     $orgId = $user->currentOrganizationId();
    //     $userOrg = \App\Models\Organization::findOrFail($orgId);
    //     $organizationId = $userOrg->parent_id ?? $userOrg->id;

    //     $release = Release::where('organization_id', $user->currentOrganizationId())
    //         ->findOrFail($id);
        
    //     $validated = $request->validate([
    //         'title' => 'sometimes|string|max:255',
    //         'version_title' => 'nullable|string|max:255',
    //         'primary_artist_name' => 'sometimes|string|max:255',
    //         'release_type' => 'sometimes|string|max:50',
    //         'upc' => 'nullable|string|max:50',
    //         'label_name' => 'nullable|string|max:255',
    //         'release_date' => 'nullable|date',
    //         'original_release_date' => 'nullable|date',
    //         'metadata' => 'nullable|array',
    //         'status' => 'nullable|string',
    //     ]);

    //     $release->update($validated);

    //     return response()->json($release);
    // }
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        
        $allowedOrgIds = $this->getAllowedOrgIds($user);

        $release = Release::whereIn('organization_id', $allowedOrgIds)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'version_title' => 'nullable|string|max:255',
            'primary_artist_name' => 'sometimes|string|max:255',
            'release_type' => 'sometimes|string|max:50',
            'upc' => 'nullable|string|max:50',
            'label_name' => 'nullable|string|max:255',
            'release_date' => 'nullable|date',
            'original_release_date' => 'nullable|date',
            'metadata' => 'nullable|array',
            'status' => 'nullable|string',

            // 🖼 artwork update
            'artwork' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        \DB::beginTransaction();

        try {
            // ❗ Remove artwork from normal update
            $releaseData = collect($validated)->except('artwork')->toArray();

            // 🧱 Update release fields
            $release->update($releaseData);

            // 🖼 Handle artwork update
            if ($request->hasFile('artwork')) {

                // delete old artwork if exists
                if ($release->artwork) {
                    \Storage::disk('public')->delete($release->artwork->file_path);
                    $release->artwork->delete();
                }

                $file = $request->file('artwork');
                $path = $file->store('artworks', 'public');

                $asset = Asset::create([
                    'id' => (string) \Str::uuid(),
                    'organization_id' => $organizationId,
                    'release_id' => $release->id,
                    'asset_type' => 'artwork',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => $user->id,
                ]);

                // attach new artwork
                $release->update([
                    'artwork_asset_id' => $asset->id
                ]);
            }

            \DB::commit();
            $release->refresh();
            return response()->json([
                'message' => 'Release updated successfully',
                'data' => $release->load('artwork')
            ]);

        } catch (\Exception $e) {

            \DB::rollBack();

            return response()->json([
                'error' => 'Update failed',
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
}