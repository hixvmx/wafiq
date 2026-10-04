<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Logo, stamp and signature. Every upload is re-encoded to PNG (drops anything hidden in
 * the file, keeps transparency), stored privately and served through BrandingController.
 */
class BrandingImages
{
    private const MAX_SIZE = ['logo' => 800, 'stamp' => 600, 'signature' => 600];

    public function store(Company $company, string $kind, UploadedFile $file): void
    {
        $png = ImageManager::gd()
            ->read($file->getRealPath())
            ->scaleDown(self::MAX_SIZE[$kind], self::MAX_SIZE[$kind])
            ->toPng();

        $path = $company->storagePath('branding')."/{$kind}-".Str::random(12).'.png';
        Storage::disk('local')->put($path, (string) $png);

        $this->delete($company, $kind);
        $company->forceFill([$kind => $path])->save();
    }

    public function delete(Company $company, string $kind): void
    {
        if ($old = $company->getAttribute($kind)) {
            Storage::disk('local')->delete($old);
            $company->forceFill([$kind => null])->save();
        }
    }
}
