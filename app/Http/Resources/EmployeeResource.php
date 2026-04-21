<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            // HRIS-native fields
            'id' => $this['id'] ?? null,
            'employee_id_number'  => $this['employee_id_number'] ?? null,
            'full_name'  => $this['fullname'] ?? null,
            'first_name' => $this['fname'] ?? null,
            'middle_name' => $this['mname'] ?? null,
            'last_name' => $this['lname'] ?? null,

            'office_desc' => $this['office_desc'] ?? null,
            'office_code' => $this['office_code'] ?? null,
            'position_title'  => $this['position_title'] ?? null,

            'type'  => $this['type'] ?? null,
            'salary_grade_id' => $this['salary_grade_id'] ?? null,
            'grade' => $this['grade'] ?? null,
            'division'  => $this['division'] ?? null,
            'unit'  => $this['unit'] ?? null,
            'salary'  => $this['salary'] ?? null,

            // keep these too if you want them
            'office_full' => $this['office_full'] ?? null,
            'position_type' => $this['position_type'] ?? null,

            // Optional aliases for your existing UI conventions
            // 'firstname' => $this['fname'] ?? null,
            // 'middlename'  => $this['mname'] ?? null,
            // 'lastname'  => $this['lname'] ?? null,
        ];
    }
}
