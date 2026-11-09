<?php

namespace App\Rules;

use App\Models\Inventory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A property number no other item has -- deleted items included, since a
 * deleted item keeps its number and can be restored. Says which case it
 * is, so someone re-entering a deleted item restores it instead.
 */
class AvailablePropertyNumber implements ValidationRule
{
    public function __construct(private int|string|null $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $holder = Inventory::withTrashed()
            ->setEagerLoads([])
            ->where('property_number', $value)
            ->when($this->ignoreId, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->first(['id', 'property_number', 'deleted_at']);

        if (! $holder) {
            return;
        }

        $fail($holder->trashed()
            ? "Property number {$value} belongs to a deleted item. Restore that item from the Deleted tab instead."
            : "Property number {$value} is already used by another item.");
    }
}
