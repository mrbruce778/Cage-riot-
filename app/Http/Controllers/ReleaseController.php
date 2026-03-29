<?php

namespace App\Http\Controllers;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Asset;

class ReleaseController extends Controller
{
    private function canManageRelease($user)
    {
        $orgId = $user->currentOrganizationId();

        return $user->hasRoleInOrganization('standard_owner', $orgId)
            || $user->hasRoleInOrganization('artist_owner', $orgId);
    }
    public function index()
    {
        $user = Auth::user();
        $releases = Release::where('organization_id', $user->currentOrganizationId())
            ->latest()
            ->get();

        return response()->json($releases);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

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

            // 👇 file validation
            'artwork' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // Step 1: Create release first
        $release = Release::create([
            ...$validated,
            'organization_id' => $user->currentOrganizationId(),
            'created_by' => $user->id,
            'status' => 'draft',
        ]);

        // Step 2: Handle artwork (if exists)
        if ($request->hasFile('artwork')) {

            $file = $request->file('artwork');

            $path = $file->store('artworks', 'public');

            $asset = Asset::create([
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

            // attach artwork
            $release->update([
                'artwork_asset_id' => $asset->id
            ]);
        }

        return response()->json([
            'message' => 'Release created successfully',
            'data' => $release->load('artwork')
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::user();

        $release = Release::where('organization_id', $user->currentOrganizationId())
            // ->with(['tracks', 'artwork'])
            ->findOrFail($id);

        return response()->json($release);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $release = Release::where('organization_id', $user->currentOrganizationId())
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
        ]);

        $release->update($validated);

        return response()->json($release);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $release = Release::where('organization_id', $user->currentOrganizationId())
            ->findOrFail($id);
        $release->tracks()->delete();
        $release->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}