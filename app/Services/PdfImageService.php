<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PdfImageService
{
    public function toBase64(string $path): ?string {
        if (!file_exists($path)) {
            return null;
        }

        $cacheKey = 'pdf_logo_base64_' . md5($path) . '_' . filemtime($path);

        return Cache::rememberForever($cacheKey, function () use ($path) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        });
    }

    public function agencyLogos(): array {
        return [
            'logo_province' => $this->toBase64(public_path('images/logo-benguet-seal-250x250.png')),
            'logo_bagong_pilipinas' => $this->toBase64(public_path('images/logo-bagong-pilipinas-250x250.png')),
            'logo_mis' => $this->toBase64(public_path('images/logo-mis-blue-250x250.png')),
        ];
    }
}