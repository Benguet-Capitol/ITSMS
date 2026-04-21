<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $with = ['brand_model', 'parent_component', 'item_type', 'internal_components'];

    protected $casts = [
        'employee_id' => 'integer',
    ];

    protected $fillable = [
        'employee_id',
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

    public function brand_model() {
      return $this->belongsTo(BrandModel::class);
    }

    // public function employee() {
    //   return $this->belongsTo(Employee::class, 'employee_id');
    // }

    public function parent_component() {
      return $this->belongsTo(Inventory::class, 'parent_component_id');
    }

    public function item_type() {
      return $this->belongsTo(ItemType::class);
    }

    public function internal_components() {
      return $this->hasMany(InventoryInternalComponent::class);
    }

    public function getComputedBrandModelSearchAttribute(): ?string {
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

    public function getEmployeeFullNameAttribute(): ?string {
        // If controller/resource attached HRIS employee payload
        $fullname = data_get($this, 'employee.fullname');
        if ($fullname) return $fullname;

        // Fallback: parent component (if it also got attached)
        $parentFullname = data_get($this, 'parent_component.employee.fullname');
        if ($parentFullname) return $parentFullname;

        return null;
    }

    public function getComputedBrandModelAttribute() {
      // Prefer the loaded relation if available
      if ($this->relationLoaded('brand_model') && $this->brand_model) {
          return $this->brand_model;
      }

      // Otherwise fallback to item_type->brand_model
      return $this->item_type?->brand_model;
    }
}
