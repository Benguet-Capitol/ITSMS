<?php

namespace App\Models\Concerns;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

/**
 * The requester's Department/Office, picked from the HRIS office list like
 * on tickets (office_id + office_code; the model's OFFICE_FIELD text column
 * keeps the description for printing, searching and older records). Types
 * that allow it (ALLOWS_OTHER_AGENCY) can instead mark the requester as
 * from another agency, picked from the agencies list -- the text column
 * then holds the agency's name.
 *
 * The model defines OFFICE_FIELD and ALLOWS_OTHER_AGENCY; the table needs
 * office_id and office_code, plus is_other_agency and agency_id when other
 * agencies are allowed.
 */
trait HasRequesterOffice
{
    public static function bootHasRequesterOffice(): void
    {
        static::saving(function (self $request) {
            if (! static::ALLOWS_OTHER_AGENCY) {
                return;
            }

            if ($request->is_other_agency) {
                $request->office_id = null;
                $request->office_code = null;
                $request->{static::OFFICE_FIELD} = $request->agency()->value('name')
                    ?? $request->{static::OFFICE_FIELD};
            } else {
                $request->agency_id = null;
            }
        });
    }

    public function initializeHasRequesterOffice(): void
    {
        $fields = ['office_id', 'office_code'];

        if (static::ALLOWS_OTHER_AGENCY) {
            $fields = [...$fields, 'is_other_agency', 'agency_id'];
            $this->mergeCasts(['is_other_agency' => 'boolean']);
            $this->attributes['is_other_agency'] ??= false;
        }

        $this->mergeFillable($fields);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Validation for the office/agency fields; the OFFICE_FIELD text column
     * keeps its own rule in each form request.
     *
     * @return array<string, array<mixed>>
     */
    public static function officeRules(): array
    {
        $rules = [
            'office_id' => ['nullable', 'string', 'max:255'],
            'office_code' => ['nullable', 'string', 'max:255'],
        ];

        if (static::ALLOWS_OTHER_AGENCY) {
            $rules['is_other_agency'] = ['boolean'];
            $rules['agency_id'] = [
                'nullable',
                'integer',
                Rule::exists('agencies', 'id'),
                'required_if_accepted:is_other_agency',
            ];
        }

        return $rules;
    }

    /** Messages for officeRules(). */
    public static function officeMessages(): array
    {
        return [
            'agency_id.required_if_accepted' => 'Select the agency.',
            'agency_id.exists' => 'Select an agency from the list.',
        ];
    }

    /**
     * Office/agency fields for API resources.
     *
     * @return array<string, mixed>
     */
    public function officeResourceFields(): array
    {
        $fields = [
            'office_id' => $this->office_id,
            'office_code' => $this->office_code,
        ];

        if (static::ALLOWS_OTHER_AGENCY) {
            $fields['is_other_agency'] = (bool) $this->is_other_agency;
            $fields['agency_id'] = $this->agency_id;
            $fields['agency'] = $this->agency_id
                ? ['id' => $this->agency_id, 'name' => $this->agency?->name, 'abbreviation' => $this->agency?->abbreviation]
                : null;
        }

        return $fields;
    }
}
