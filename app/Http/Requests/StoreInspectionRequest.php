<?php

namespace App\Http\Requests;

use App\Models\InspectionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreInspectionRequest extends FormRequest
{
    /** Most items one purchase is expected to list. */
    public const MAX_ITEMS = 50;

    /** Most inspectors one request can list. */
    public const MAX_INSPECTORS = 20;

    /** Whether this form records the inspection (item results and remarks). */
    protected bool $recordsInspection = false;

    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Control number and status are server-assigned on create; item
     * results are recorded later, on update.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'date_of_request' => ['required', 'date'],
            'end_user_office' => ['required', 'string', 'max:255'],
            ...InspectionRequest::officeRules(),

            'procurement_mode' => ['required', 'string', Rule::in(array_keys(InspectionRequest::PROCUREMENT_MODES))],
            'po_number' => ['nullable', 'string', 'max:50', 'required_if:procurement_mode,purchase_order'],
            'supplier' => ['required', 'string', 'max:255'],

            'inspector_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_INSPECTORS],
            'inspector_ids.*' => ['integer', 'distinct', $this->inspectorExistsRule()],

            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['array'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            ...InspectionRequest::officeMessages(),
            'po_number.required_if' => 'Enter the PO number for a purchase order.',
            'inspector_ids.required' => 'Select at least one inspector.',
            'inspector_ids.min' => 'Select at least one inspector.',
            'inspector_ids.max' => 'An inspection can list at most '.self::MAX_INSPECTORS.' inspectors.',
            'inspector_ids.*.exists' => 'This inspector is not on the active list.',
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.max' => 'An inspection can list at most '.self::MAX_ITEMS.' items.',
            'items.*.description.required' => 'Describe the item.',
            'items.*.quantity.required' => 'Enter the quantity.',
            'items.*.quantity.min' => 'The quantity must be at least 1.',
            'items.*.unit_cost.min' => 'The unit cost cannot be negative.',
        ];
    }

    /** New picks must be active inspectors. */
    protected function inspectorExistsRule(): Exists
    {
        return Rule::exists('inspectors', 'id')->where('is_active', true);
    }

    /** @return list<int> */
    public function inspectorIds(): array
    {
        return array_map('intval', $this->validated('inspector_ids'));
    }

    /**
     * Validated data (minus the inspectors, which go to the pivot) with the
     * item list normalized (trimmed text, blank as null, unit costs to two
     * decimals) and the PO number kept only for purchase orders.
     *
     * @return array<string, mixed>
     */
    public function inspectionAttributes(): array
    {
        $data = $this->safe()->except('inspector_ids');

        $data['po_number'] = $data['procurement_mode'] === 'purchase_order'
            ? self::blankToNull($data['po_number'] ?? null)
            : null;
        $data['supplier'] = trim($data['supplier']);

        $data['items'] = collect($data['items'])
            ->map(fn (array $item) => [
                'description' => trim($item['description']),
                'brand' => self::blankToNull($item['brand'] ?? null),
                'quantity' => (int) $item['quantity'],
                'unit_cost' => isset($item['unit_cost']) && $item['unit_cost'] !== '' ? round((float) $item['unit_cost'], 2) : null,
                // `items.*` passes each item through whole, so results are
                // only taken where UpdateInspectionRequest validates them.
                'result' => $this->recordsInspection ? ($item['result'] ?? null) : null,
                'remarks' => $this->recordsInspection ? self::blankToNull($item['remarks'] ?? null) : null,
            ])
            ->values()
            ->all();

        return $data;
    }

    private static function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
