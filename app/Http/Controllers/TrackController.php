<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use App\Models\Organization;

class TrackController extends Controller
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

    // ✅ Create Track
    public function store(Request $request, $releaseId)
    {
        $user = Auth::user();
        $userOrg = Organization::findOrFail($user->currentOrganizationId());
        $normalizedOrgId = $userOrg->parent_id ?? $userOrg->id;
        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $release = Release::where('organization_id', $normalizedOrgId)
            ->findOrFail($releaseId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'audio' => 'required|file|mimes:wav,flac|max:51200',
            'artwork' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();

        try {
            // ✅ Track created WITHOUT track_number
            $track = $release->tracks()->create([
                'title' => $validated['title'],
                'organization_id' => $normalizedOrgId,
                'created_by' => $user->id,
            ]);

            // 🎵 Audio
            if ($request->hasFile('audio')) {
                $file = $request->file('audio');
                $path = $file->store('tracks/audio', 'public');

                Asset::create([
                    'organization_id' => $normalizedOrgId,
                    'release_id' => $releaseId,
                    'track_id' => $track->id,
                    'asset_type' => 'audio',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => $user->id,
                ]);
            }

            // 🖼️ Artwork
            if ($request->hasFile('artwork')) {
                $file = $request->file('artwork');
                $path = $file->store('tracks/artwork', 'public');

                Asset::create([
                    'organization_id' => $normalizedOrgId,
                    'release_id' => $releaseId,
                    'track_id' => $track->id,
                    'asset_type' => 'artwork',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => $user->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'track' => $track->load(['audio', 'artwork'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Something went wrong',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // ✅ List Tracks
    public function index($releaseId)
    {
        $user = Auth::user();
        $orgId = $user->currentOrganizationId();

        $tracks = Track::where('organization_id', $orgId)
            ->where('release_id', $releaseId)
            ->orderBy('track_number')
            ->get();

        return response()->json($tracks);
    }

    // ✅ Update Track
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $orgId = $user->currentOrganizationId();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $track = Track::where('organization_id', $orgId)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'track_number' => 'sometimes|integer|min:1',
        ]);

        // ✅ Check uniqueness if track_number is being updated
        if (isset($validated['track_number'])) {
            $exists = Track::where('release_id', $track->release_id)
                ->where('track_number', $validated['track_number'])
                ->where('id', '!=', $track->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => 'Track number already exists for this release'
                ], 422);
            }
        }

        $track->update($validated);

        return response()->json($track);
    }

    // ✅ Delete Track
    public function destroy($id)
    {
        $user = Auth::user();
        $orgId = $user->currentOrganizationId();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $track = Track::where('organization_id', $orgId)
            ->findOrFail($id);

        $track->delete();

        return response()->json(['message' => 'Deleted']);
    }
    public function show($id)
    {
        $user = auth()->user();

        $track = \App\Models\Track::whereHas('release', function ($q) use ($user) {
            $q->where('organization_id', $user->currentOrganizationId());
        })->findOrFail($id);

        return response()->json($track);
    }
}