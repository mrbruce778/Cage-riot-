<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use App\Models\Release;
use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use App\Models\Organization;
use Illuminate\Support\Str;

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

        // 🔐 Permission check
        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $release = Release::findOrFail($releaseId);
        // ✅ Validation
        $validated = $request->validate([
            'title' => 'required|string|max:255',

            // metadata
            'primary_genre' => 'required|string',
            'secondary_genre' => 'nullable|string',
            'version' => 'nullable|string',
            'language' => 'nullable|string',
            'lyrics' => 'nullable|string',

            'is_explicit' => 'boolean',
            'preview_start' => 'nullable|integer',
            'track_origin' => 'required|in:original,public_domain,cover',

            'track_properties' => 'nullable|array',

            // 🎧 audio (required)
            'audio_file_path' => 'required|string',
            'audio_file_name' => 'required|string',
            'audio_mime_type' => 'required|string',
            'audio_file_size' => 'required|integer',

            // 📄 license (conditional)
            'sample_license_file_path' => 'nullable|string',
            'sample_license_file_name' => 'nullable|string',
            'sample_license_mime_type' => 'nullable|string',
            'sample_license_file_size' => 'nullable|integer',

            // copyright
            'copyright_year' => 'nullable|integer',
            'copyright_owner' => 'nullable|string',
        ]);

        // 🧠 Normalize organization
        $orgId = $user->currentOrganizationId();
        $userOrg = Organization::findOrFail($orgId);
        $organizationId = $userOrg->parent_id ?? $userOrg->id;

        DB::beginTransaction();

        try {

            // 🧠 Clean properties
            $properties = array_values(array_filter($validated['track_properties'] ?? []));

            // 🎵 Create Track
            $trackData = collect($validated)->except([
                'audio_file_path',
                'audio_file_name',
                'audio_mime_type',
                'audio_file_size',
                'sample_license_file_path',
                'sample_license_file_name',
                'sample_license_mime_type',
                'sample_license_file_size',
            ])->toArray();

            $track = Track::create([
                ...$trackData,
                'release_id' => $releaseId, // ✅ THIS IS THE KEY FIX
                'track_properties' => $properties,
                'organization_id' => $organizationId,
                'created_by' => $user->id,
            ]);

            // 🎧 AUDIO ASSET (REQUIRED)
            Asset::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'track_id' => $track->id,
                'asset_type' => 'audio',
                'file_name' => $validated['audio_file_name'],
                'file_path' => $validated['audio_file_path'],
                'mime_type' => $validated['audio_mime_type'],
                'file_size' => $validated['audio_file_size'],
                'created_by' => $user->id,
            ]);

            // 📄 LICENSE (ONLY IF samples_or_stock)
            if (
                in_array('samples_or_stock', $properties) &&
                $request->filled('sample_license_file_path')
            ) {
                Asset::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organizationId,
                    'track_id' => $track->id,
                    'asset_type' => 'sample_license',
                    'file_name' => $validated['sample_license_file_name'],
                    'file_path' => $validated['sample_license_file_path'],
                    'mime_type' => $validated['sample_license_mime_type'],
                    'file_size' => $validated['sample_license_file_size'],
                    'created_by' => $user->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Track created successfully',
                'data' => $track->load('assets')
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Failed to create track',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function index($releaseId)
    {
        $user = Auth::user();

        $userOrg = Organization::findOrFail($user->currentOrganizationId());
        $normalizedOrgId = $userOrg->parent_id ?? $userOrg->id;

        $tracks = Track::where('organization_id', $normalizedOrgId)
            ->where('release_id', $releaseId)
            ->orderBy('track_number')
            ->with(['audio', 'artwork','creator:id,name'])
            ->get();

        return response()->json($tracks);
    }

    // ✅ Update Track
    public function update(Request $request,  $id)
    {
        $user = Auth::user();

        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $userOrg = Organization::findOrFail($user->currentOrganizationId());
        $organizationId = $userOrg->parent_id ?? $userOrg->id;

        $track = Track::where('organization_id', $organizationId)
            ->findOrFail($id);

        // ✅ Full validation (same as store but nullable)
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',

            'primary_genre' => 'nullable|string',
            'secondary_genre' => 'nullable|string',
            'version' => 'nullable|string',
            'language' => 'nullable|string',
            'lyrics' => 'nullable|string',

            'is_explicit' => 'nullable|boolean',
            'preview_start' => 'nullable|integer',
            'track_origin' => 'nullable|in:original,public_domain,cover',

            'track_properties' => 'nullable|array',

            // 🎧 audio (optional for update)
            'audio_file_path' => 'nullable|string',
            'audio_file_name' => 'nullable|string',
            'audio_mime_type' => 'nullable|string',
            'audio_file_size' => 'nullable|integer',

            // 📄 license
            'sample_license_file_path' => 'nullable|string',
            'sample_license_file_name' => 'nullable|string',
            'sample_license_mime_type' => 'nullable|string',
            'sample_license_file_size' => 'nullable|integer',

            'copyright_year' => 'nullable|integer',
            'copyright_owner' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {

            // ✅ Clean properties
            $allowedProperties = [
                'remix_or_derivative',
                'samples_or_stock',
                'compilation',
                'alternate_version',
                'special_genre',
                'non_musical_content',
                'includes_ai',
                'none_of_the_above'
            ];

            if ($request->has('track_properties')) {
                $validated['track_properties'] = array_values(array_intersect(
                    $request->track_properties ?? [],
                    $allowedProperties
                ));
            }
            $trackData = collect($validated)->except([
                'audio_file_path',
                'audio_file_name',
                'audio_mime_type',
                'audio_file_size',
                'sample_license_file_path',
                'sample_license_file_name',
                'sample_license_mime_type',
                'sample_license_file_size',
            ])->toArray();

            $track->update($trackData);
            // 🎧 Replace audio (if new uploaded)
            if ($request->filled('audio_file_path')) {

                // delete old audio asset (optional)
                Asset::where('track_id', $track->id)
                    ->where('asset_type', 'audio')
                    ->delete();

                Asset::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organizationId,
                    'track_id' => $track->id,
                    'asset_type' => 'audio',
                    'file_name' => $request->audio_file_name,
                    'file_path' => $request->audio_file_path,
                    'mime_type' => $request->audio_mime_type,
                    'file_size' => $request->audio_file_size,
                    'created_by' => $user->id,
                ]);
            }

            // 📄 License logic
            $properties = $validated['track_properties'] ?? $track->track_properties ?? [];

            if (in_array('samples_or_stock', $properties)) {

                if ($request->filled('sample_license_file_path')) {

                    // delete old license
                    Asset::where('track_id', $track->id)
                        ->where('asset_type', 'sample_license')
                        ->delete();

                    Asset::create([
                        'id' => (string) Str::uuid(),
                        'organization_id' => $organizationId,
                        'track_id' => $track->id,
                        'asset_type' => 'sample_license',
                        'file_name' => $request->sample_license_file_name,
                        'file_path' => $request->sample_license_file_path,
                        'mime_type' => $request->sample_license_mime_type,
                        'file_size' => $request->sample_license_file_size,
                        'created_by' => $user->id,
                    ]);
                }

            } else {
                // ❌ remove license if no longer needed
                Asset::where('track_id', $track->id)
                    ->where('asset_type', 'sample_license')
                    ->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Track updated successfully',
                'data' => $track->load('assets')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to update track',
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
        $organizationId = $userOrg->parent_id ?? $userOrg->id;

        $track = Track::where('organization_id', $organizationId)
            ->with('assets')
            ->findOrFail($id);

        DB::beginTransaction();

        try {

            // 🔌 R2 client
            $client = new S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.r2.region'),
                'endpoint' => config('filesystems.disks.r2.endpoint'),
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => config('filesystems.disks.r2.key'),
                    'secret' => config('filesystems.disks.r2.secret'),
                ],
            ]);

            // 🧹 Delete files from R2
            foreach ($track->assets as $asset) {
                if ($asset->file_path) {
                    try {
                        $client->deleteObject([
                            'Bucket' => config('filesystems.disks.r2.bucket'),
                            'Key' => $asset->file_path,
                        ]);
                    } catch (\Exception $e) {
                        // optional: log but don't break delete
                        \Log::warning('R2 delete failed: ' . $e->getMessage());
                    }
                }
            }

            // 🗑 Delete assets from DB
            Asset::where('track_id', $track->id)->delete();

            // 🗑 Delete track
            $track->delete();

            DB::commit();

            return response()->json([
                'message' => 'Track deleted successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Failed to delete track',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function show($id)
    {
        $user = auth()->user();

        $userOrg = Organization::findOrFail($user->currentOrganizationId());
        $normalizedOrgId = $userOrg->parent_id ?? $userOrg->id;

        $track = Track::where('id', $id)
            ->where('organization_id', $normalizedOrgId)
            ->with(['audio', 'creator:id,name']) // ✅ load assets
            ->firstOrFail();
       
        if ($track->audio && $track->audio->file_path) {

            if (str_starts_with($track->audio->file_path, 'tracks/')) {

                $track->audio->file_path = Storage::disk('s3')->temporaryUrl(
                    $track->audio->file_path,
                    now()->addMinutes(10)
                );

            }
        }
        return response()->json($track);
    }
    public function uploadAsset(Request $request, Track $track)
    {
        $request->validate([
            'audio' => 'nullable|file|mimes:wav,flac,mp3|max:51200',
            'artwork' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $user = Auth::user();
        $userOrg = Organization::findOrFail($user->currentOrganizationId());
        $normalizedOrgId = $userOrg->parent_id ?? $userOrg->id;
        if (!$this->canManageRelease($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $allowedOrgIds = $this->getAllowedOrgIds($user);

        $track = Track::where('organization_id', $normalizedOrgId)
            ->findOrFail($track->id);

        DB::beginTransaction();

        try {

            $responses = [];

            // 🎵 AUDIO
            if ($request->hasFile('audio')) {
                $file = $request->file('audio');

                // delete old audio
                $existing = Asset::where('track_id', $track->id)
                    ->where('asset_type', 'audio')
                    ->first();

                if ($existing) {
                    Storage::disk('public')->delete($existing->file_path);
                    $existing->delete();
                }

                $path = $file->store('tracks/audio', 'public');

                $audioAsset = Asset::create([
                    'id' => (string) \Str::uuid(),
                    'organization_id' => $normalizedOrgId,
                    'track_id' => $track->id,
                    'asset_type' => 'audio',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => $user->id,
                ]);

                $responses['audio'] = $audioAsset;
            }

            // 🖼️ ARTWORK
            if ($request->hasFile('artwork')) {
                $file = $request->file('artwork');

                // delete old artwork
                $existing = Asset::where('track_id', $track->id)
                    ->where('asset_type', 'artwork')
                    ->first();

                if ($existing) {
                    Storage::disk('public')->delete($existing->file_path);
                    $existing->delete();
                }

                $path = $file->store('tracks/artwork', 'public');

                $artworkAsset = Asset::create([
                    'id' => (string) \Str::uuid(),
                    'organization_id' => $normalizedOrgId,
                    'track_id' => $track->id,
                    'asset_type' => 'artwork',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => $user->id,
                ]);

                $responses['artwork'] = $artworkAsset;
            }

            DB::commit();

            if (empty($responses)) {
                return response()->json([
                    'message' => 'No file uploaded'
                ]);
            }

            return response()->json([
                'message' => 'Assets uploaded successfully',
                'data' => $responses
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}