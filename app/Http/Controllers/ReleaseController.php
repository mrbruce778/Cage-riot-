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
                    'creator:id,name'
            ])
            ->latest()
            ->get();

        return response()->json($releases);
    }
    public function oldstore(Request $request)
    {
        $user = Auth::user();

        // 🔐 Permission check
        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // 🧠 Decode metadata if string
        if ($request->has('metadata')) {
            $request->merge([
                'metadata' => json_decode($request->metadata, true)
            ]);
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

            // ✅ NEW: artwork upload like track
            'artwork' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // 🧠 Normalize organization
        $orgId = $user->currentOrganizationId();
        $userOrg = Organization::findOrFail($orgId);
        $organizationId = $userOrg->parent_id ?? $userOrg->id;

        DB::beginTransaction();

        try {

            $releaseData = collect($validated)->except(['artwork'])->toArray();

            // 🧱 Create release
            $release = Release::create([
                ...$releaseData,
                'organization_id' => $organizationId,
                'created_by' => $user->id,
                'status' => 'draft',
            ]);

            // 🖼️ Artwork upload (LIKE TRACK API)
            if ($request->hasFile('artwork')) {

                $file = $request->file('artwork');
                $path = $file->store('releases/artwork', 'public');

                $asset = Asset::create([
                    'organization_id' => $organizationId,
                    'release_id' => $release->id,
                    'asset_type' => 'artwork',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => $user->id,
                ]);

                // 🔗 Attach artwork to release
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
            'file_path' => 'nullable|string',
            'file_name' => 'nullable|string',
            'mime_type' => 'nullable|string',
            'file_size' => 'nullable|integer',
            // 'artwork' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // 🧠 Normalize organization (parent or self)
        $orgId = $user->currentOrganizationId();
        $userOrg = Organization::findOrFail($orgId);
        $organizationId = $userOrg->parent_id ?? $userOrg->id;

        DB::beginTransaction();

        try {
           
            $releaseData = collect($validated)->except([
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

            // 🖼 Upload artwork if exists
            if ($request->filled('file_path')) {

                $path = $request->input('file_path');

                $asset = Asset::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organizationId,
                    'release_id' => $release->id,
                    'asset_type' => 'artwork',
                    'file_name' => $request->input('file_name'),
                    'file_path' => $path,
                    'mime_type' => $request->input('mime_type'),
                    // 'file_size' => $file->getSize(),
                    'file_size' => $request->input('file_size'),
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
            ->with(['tracks', 'artwork','creator:id,name'])
            ->findOrFail($id);
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

        // if ($request->has('metadata')) {
        //     $request->merge([
        //         'metadata' => json_decode($request->metadata, true)
        //     ]);
        // }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'version_title' => 'nullable|string|max:255',
            'primary_artist_name' => 'nullable|string|max:255',
            'release_type' => 'nullable|string|max:50',
            'upc' => 'nullable|string|max:50',
            'label_name' => 'nullable|string|max:255',
            'release_date' => 'nullable|date',
            'original_release_date' => 'nullable|date',
            'metadata' => 'nullable|array',
            'status' => 'nullable|string',

            'file_path' => 'nullable|string',
            'file_name' => 'nullable|string',
            'mime_type' => 'nullable|string',
            'file_size' => 'nullable|integer',
        ]);

        DB::beginTransaction();

        try {

            // 🧱 Update release fields
            $release->update(collect($validated)->except([
                'file_path',
                'file_name',
                'mime_type',
                'file_size'
            ])->toArray());

            // 🖼 Update artwork if provided
            if ($request->filled('file_path')) {

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

                // 🔁 Replace artwork
                $release->update([
                    'artwork_asset_id' => $asset->id
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Release updated successfully',
                'data' => $release->fresh()->load('artwork')
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
}