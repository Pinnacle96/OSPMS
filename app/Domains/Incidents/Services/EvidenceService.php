<?php

namespace App\Domains\Incidents\Services;

use App\Domains\Identity\Models\User;
use App\Domains\System\Models\MediaAttachment;
use App\Http\Requests\Incidents\UploadEvidenceRequest;
use App\Support\ConfirmedActionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EvidenceService
{
    public function fingerprint(?UploadedFile $file): ?array
    {
        return $file ? ['hash' => hash_file('sha256', $file->getRealPath()), 'size' => $file->getSize(), 'name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), 'mime' => $file->getMimeType()] : null;
    }

    public function stored(?UploadedFile $file, callable $work): Model
    {
        if (! $file) {
            return $work(null, null);
        }
        $meta = $this->fingerprint($file);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$meta['mime']] ?? null;
        abort_unless($ext, 422, 'Unsupported evidence type.');
        $path = 'evidence/'.Str::ulid().'.'.$ext;
        abort_unless(Storage::disk('local')->putFileAs('evidence', $file, basename($path)), 503, 'Evidence storage is unavailable.');
        try {
            return $work($path, $meta);
        } finally {
            // A replay or rolled-back transaction must not leave an orphan file. Retain committed evidence.
            if (! MediaAttachment::where('disk', 'local')->where('path', $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    public function attach(User $u, Model $r, string $path, array $meta): MediaAttachment
    {
        $m = $r->evidence()->create(['category' => strtolower(class_basename($r)).'_evidence', 'disk' => 'local', 'path' => $path, 'original_name' => $meta['name'], 'mime_type' => $meta['mime'], 'size_bytes' => $meta['size'], 'file_hash' => $meta['hash'], 'uploaded_by' => $u->id, 'created_at' => now()]);
        activity('incidents')->causedBy($u)->performedOn($r)->withProperties(['evidence' => $m->public_id, 'sha256' => $meta['hash']])->log('evidence_uploaded');

        return $m;
    }

    public function upload(User $u, Model $r, array $input): Model
    {
        Gate::forUser($u)->authorize('view', $r);
        abort_unless($u->can('upload_evidence'), 403);
        $data = validator($input, UploadEvidenceRequest::inputRules())->validate();

        // Replays still require fresh access, while terminal states reject new evidence.
        return $this->stored($data['file'], function ($path, $meta) use ($u, $r, $data) {
            return app(ConfirmedActionService::class)->execute($u, $data['idempotency_key'], 'upload_evidence', ['subject' => $r->getMorphClass().':'.$r->public_id, 'file' => $meta], MediaAttachment::class, function () use ($u, $r, $path, $meta) {
                $r = $r->newQuery()->whereKey($r->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($u)->authorize('evidence', $r);

                return $this->attach($u, $r, $path, $meta);
            });
        });
    }
}
