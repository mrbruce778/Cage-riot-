<?php

namespace App\Http\Controllers;

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
        $user = Auth::user();
        if (!$user || $release->organization_id !== $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        DB::beginTransaction();
        try{
            if ($release->artwork) {
                Storage::disk('public')->delete($release->artwork->file_path);
                $release->artwork->delete();
            }
            

        

        $file = $request->file('file');

        $path = $file->store('artworks', 'public');

        $asset = Asset::create([
            'organization_id' => $release->organization_id,
            'release_id' => $release->id,
            'asset_type' => 'image',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'created_by' => Auth::id(),
        ]);

        $release->update([
            'artwork_asset_id' => $asset->id
        ]);
        DB::commit();
        return response()->json([
            'message' => 'Artwork uploaded successfully',
            'asset' => $asset,
        ]);
                }catch(\Exception $e){
            DB::rollBack();
            return response()->json(['error' => 'upload failed'], 500);
        }

    }
}