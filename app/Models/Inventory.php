<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Inventory extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        // public_id is the safe, non-guessable identifier external
        // surfaces (Sprint 8's QR codes) reference instead of the raw
        // sequential id -- generated once here rather than mass-
        // assignable, so it can never be set/overridden via request input.
        static::creating(function (Inventory $inventory) {
            $inventory->public_id ??= (string) Str::ulid();
        });
    }

    protected $with = ['brand_model', 'parent_component', 'item_type', 'internal_components'];

    protected $casts = [
        'employee_id' => 'integer',
        'office_id' => 'integer',
    ];

    protected $fillable = [
        'employee_id',
        'office_id',
        'office_code',
        'office_name',
        'division_id',
        'division_name',
        'item_type_id',
        'brand_model_id',
        'parent_component_id',
        'ip_address',
        'mac_address',
        'remarks',
        'operating_system_name',
        'os_license_number',
        'anti_virus_name',
        'anti_virus_license_number',
        'microsoft_office_name',
        'ms_office_license_number',
        'other_installed_applications',
        'property_number',
        'date_acquired',
        'warranty_expiration_date',
        'serial_number',
        'status',
    ];

    public function brand_model()
    {
        return $this->belongsTo(BrandModel::class);
    }

    // public function employee() {
    //   return $this->belongsTo(Employee::class, 'employee_id');
    // }

    // A component still shows the asset it belonged to after that asset
    // is deleted, and goes back under it if it's restored.
    public function parent_component()
    {
        return $this->belongsTo(Inventory::class, 'parent_component_id')->withTrashed();
    }

    // The reverse of parent_component: components attached to this asset.
    public function child_components()
    {
        return $this->hasMany(Inventory::class, 'parent_component_id');
    }

    public function item_type()
    {
        return $this->belongsTo(ItemType::class);
    }

    public function internal_components()
    {
        return $this->hasMany(InventoryInternalComponent::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'inventory_id');
    }

    public function getComputedBrandModelSearchAttribute(): ?string
    {
        if (! $this->relationLoaded('item_type')) {
            return null;
        }

        $type = $this->item_type?->type;

        $brandName = $this->relationLoaded('brand_model')
            ? $this->brand_model?->brand?->name
            : null;

        return $brandName
            ? "{$brandName} {$type}"
            : $type;
    }

    public function getEmployeeFullNameAttribute(): ?string
    {
        // If controller/resource attached HRIS employee payload
        $fullname = data_get($this, 'employee.fullname');
        if ($fullname) {
            return $fullname;
        }

        // Fallback: parent component (if it also got attached)
        $parentFullname = data_get($this, 'parent_component.employee.fullname');
        if ($parentFullname) {
            return $parentFullname;
        }

        return null;
    }

    public function getComputedBrandModelAttribute()
    {
        // Prefer the loaded relation if available
        if ($this->relationLoaded('brand_model') && $this->brand_model) {
            return $this->brand_model;
        }

        // Otherwise fallback to item_type->brand_model
        return $this->item_type?->brand_model;
    }

    public function activityLabel(): string
    {
        return (string) $this->property_number;
    }

    /**
     * The item with names instead of ids and its internal components
     * (LogsActivity). The actual user's name comes from the HRIS list
     * only if it's already cached -- the log never waits on HRIS.
     */
    public function activitySnapshot(): array
    {
        $brandModel = $this->brand_model_id ? BrandModel::with('brand')->find($this->brand_model_id) : null;

        return [
            ...self::normalizeActivityValues(Arr::except($this->getAttributes(), [
                'id', 'public_id', 'created_at', 'updated_at', 'office_id', 'division_id',
                'item_type_id', 'brand_model_id', 'parent_component_id', 'employee_id',
            ]), $this),
            'actual_user' => $this->activityEmployeeName(),
            'item_type' => $this->item_type_id ? ItemType::whereKey($this->item_type_id)->value('type') : null,
            'brand_model' => $brandModel ? trim(($brandModel->brand?->name ?? '').' '.$brandModel->name) : null,
            'parent_component' => $this->parent_component_id
                ? self::withTrashed()->setEagerLoads([])->whereKey($this->parent_component_id)->value('property_number')
                : null,
            'internal_components' => $this->internal_components()
                ->with('brand_model.brand')
                ->get()
                ->map(fn (InventoryInternalComponent $component) => collect([
                    trim(($component->brand_model?->brand?->name ?? '').' '.($component->brand_model?->name ?? '')),
                    'x'.($component->quantity ?? 1),
                    $component->specific_serial_number ? "S/N {$component->specific_serial_number}" : null,
                    $component->slot ? "slot {$component->slot}" : null,
                    $component->notes,
                ])->filter()->implode(' '))
                ->sort()
                ->values()
                ->all(),
        ];
    }

    private function activityEmployeeName(): ?string
    {
        if (! $this->employee_id) {
            return null;
        }

        $employee = collect(Cache::get('hris:employees:all', []))
            ->first(fn ($e) => (int) ($e['id'] ?? 0) === (int) $this->employee_id);

        return $employee['fullname'] ?? "HRIS employee #{$this->employee_id}";
    }

    public function getResolvedOfficeNameAttribute(): ?string
    {
        return $this->office_name
            ?: $this->parent_component?->office_name;
    }
}
