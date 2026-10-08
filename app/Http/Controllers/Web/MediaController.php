<?php

namespace App\Http\Controllers\Web;

use App\Domains\System\Models\MediaAttachment;
use App\Domains\System\Services\CatalogDefinition;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    public function store(Request $request, string $catalog, string $record, CatalogDefinition $d)
    {
        $r = $d->record($catalog, $record);
        Gate::authorize('update', $r);
        $categories = ['operators' => ['identity_document'], 'drivers' => ['driver_photo', 'identity_document'], 'vehicles' => ['vehicle_photo', 'vehicle_document']];
        $data = $request->validate(['category' => ['required', Rule::in($categories[$catalog])], 'file' => 'required|file|mimes:jpg,jpeg,png,pdf|extensions:jpg,jpeg,png,pdf|max:5120']);
        if (str_ends_with($data['category'], 'photo')) {
            $request->validate(['file' => 'image|mimes:jpg,jpeg,png']);
        }
        $file = $request->file('file');
        $path = 'registry/'.Str::ulid().'.'.$file->extension();
        $stored = Storage::disk('local')->putFileAs('registry', $file, basename($path));
        abort_unless($stored, 503, 'Document storage is unavailable.');
        try {
            DB::transaction(function () use ($request, $r, $data, $file, $path) {
                $media = $r->documents()->create(['category' => $data['category'], 'disk' => 'local', 'path' => $path, 'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'file_hash' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $request->user()->id, 'created_at' => now()]);
                activity('documents')->causedBy($request->user())->performedOn($r)->withProperties(['document' => $media->public_id, 'category' => $data['category']])->log('document_uploaded');
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return back()->with('success', 'Private document uploaded.');
    }

    public function show(Request $request, string $catalog, string $record, MediaAttachment $media, CatalogDefinition $d)
    {
        $r = $d->record($catalog, $record);
        Gate::authorize('view', $r);
        abort_unless($media->attachable_type === $r->getMorphClass() && $media->attachable_id === $r->id, 404);
        activity('documents')->causedBy($request->user())->performedOn($r)->withProperties(['document' => $media->public_id])->log('document_downloaded');

        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($request->boolean('preview') && in_array($media->mime_type, ['image/jpeg', 'image/png'], true)) {
            return Storage::disk($media->disk)->response($media->path, null, $headers, 'inline');
        }

        return Storage::disk($media->disk)->download($media->path, 'document-'.$media->public_id.'.'.pathinfo($media->path, PATHINFO_EXTENSION), $headers);
    }
}
