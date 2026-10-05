<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Profile pictures. Like the branding images, every upload is re-encoded (here to a
 * 256×256 square PNG), stored privately and served through ProfileController::avatar.
 * Users are not company data (in the SaaS one person can be in several companies),
 * so the files live under users/{id}.
 */
class UserAvatar
{
    private const SIZE = 256;

    public function store(User $user, UploadedFile $file): void
    {
        $png = ImageManager::gd()
            ->read($file->getRealPath())
            ->cover(self::SIZE, self::SIZE)
            ->toPng();

        $path = "users/{$user->id}/avatar-".Str::random(12).'.png';
        Storage::disk('local')->put($path, (string) $png);

        $this->delete($user);
        $user->forceFill(['avatar' => $path])->save();
    }

    public function delete(User $user): void
    {
        if ($old = $user->avatar) {
            Storage::disk('local')->delete($old);
            $user->forceFill(['avatar' => null])->save();
        }
    }
}
