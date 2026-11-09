<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One property from PGSO's property management system, as of the last
 * `pms:sync` (docs/inventory-reconciliation.md). Read-only copy: the id
 * is PMS's property_id, and nothing in ITSMS edits these rows.
 */
class PmsProperty extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'property_price' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    /** How property numbers are compared: upper case, whitespace removed. */
    public static function numberKey(?string $number): string
    {
        return strtoupper(preg_replace('/\s+/u', '', (string) $number));
    }

    /**
     * The number without the prefixes and suffixes one side adds and the
     * other doesn't ("ICS 1061-98111", "1041-41754 TF", "1011-38577-"):
     * equal cores are a likely match that a person confirms.
     */
    public static function numberCore(?string $number): string
    {
        $number = strtoupper(trim((string) $number));
        $number = preg_replace('/^\(?(ICS|TF|PAR)\)?[\s-]+/', '', $number);
        $number = preg_replace('/\s+(TF|ICS|NO\s*PN)$/', '', $number);

        return rtrim(self::numberKey($number), '-');
    }

    /**
     * The first serial number written in the free-text details ("Serial
     * No.: X", "SN: X", "S/N X"...), or null. Details can list several
     * items; the comparison checks the whole text, this is what gets
     * copied when someone accepts the PMS serial.
     */
    public static function serialFromDetails(?string $details): ?string
    {
        $pattern = '/(?:serial\s*(?:no\.?|number|#)?|\bs\/?n)\s*[:.]?\s*(?:\d\.\)\s*)?([A-Z0-9][A-Z0-9\-\/]{4,})/i';

        return preg_match($pattern, (string) $details, $match) ? strtoupper($match[1]) : null;
    }
}
