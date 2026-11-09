<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Server-side checks for uploaded pictures (user photos, brand-model
 * images). The type is read from the file's content, not its name or the
 * browser's say-so; SVG is refused because it can carry script and these
 * files are served publicly; huge pixel sizes are refused before anything
 * tries to load them.
 */
class ImageUpload
{
    public const TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_KILOBYTES = 2048;

    public const MAX_PIXELS = 4096;

    /** @return list<mixed> */
    public static function rules(): array
    {
        return [
            'nullable',
            File::image()
                ->types(self::TYPES)
                ->max(self::MAX_KILOBYTES)
                ->dimensions(Rule::dimensions()->maxWidth(self::MAX_PIXELS)->maxHeight(self::MAX_PIXELS)),
            'mimetypes:image/jpeg,image/png,image/webp',
        ];
    }
}
