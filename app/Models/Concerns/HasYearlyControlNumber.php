<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Server-assigned "YYYY-NNNN" control numbers that restart each year,
 * matching the "Control No.: 2026 - ____" box on the PLGU request forms.
 * The model must have a unique `control_number` column.
 */
trait HasYearlyControlNumber
{
    /**
     * Must run inside a transaction: the row lock keeps two simultaneous
     * creates from taking the same number.
     */
    public static function nextControlNumber(?Carbon $date = null): string
    {
        $year = ($date ?? now())->format('Y');

        $last = static::query()
            ->where('control_number', 'like', "{$year}-%")
            ->lockForUpdate()
            ->pluck('control_number')
            ->map(fn (string $number) => (int) substr($number, strlen($year) + 1))
            ->max() ?? 0;

        return sprintf('%s-%04d', $year, $last + 1);
    }

    /**
     * Creates the record with the next control number. Any client-supplied
     * control number in $attributes is overridden.
     */
    public static function createWithControlNumber(array $attributes): static
    {
        return DB::transaction(fn () => static::create([
            ...$attributes,
            'control_number' => static::nextControlNumber(),
        ]));
    }
}
