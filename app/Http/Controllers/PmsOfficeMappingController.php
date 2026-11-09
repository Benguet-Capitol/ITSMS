<?php

namespace App\Http\Controllers;

use App\Models\PmsOfficeMapping;
use App\Models\PmsProperty;
use App\Services\HrisClientService;
use App\Services\PmsReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Which HRIS office each PMS office name stands for
 * (docs/inventory-reconciliation.md). PMS writes "NORTHERN BENGUET
 * DISTRICT HOSPITAL" where ITSMS has NBDH; once mapped, the two count as
 * the same office, and "Use PMS office" knows which HRIS office to set.
 */
class PmsOfficeMappingController extends Controller
{
    public function index(PmsReconciliation $reconciliation)
    {
        $returnedOffice = strtoupper((string) config('services.pms.returned_office'));
        $mappings = PmsOfficeMapping::all()->keyBy(fn ($m) => strtoupper($m->pms_office_code));
        $suggestions = $reconciliation->officeSuggestions();

        $offices = DB::table('pms_properties')
            ->whereNotNull('office_code')
            ->select('office_code', DB::raw('count(*) as property_count'))
            ->groupBy('office_code')
            ->orderBy('office_code')
            ->get()
            ->reject(fn ($row) => strtoupper($row->office_code) === $returnedOffice)
            ->map(function ($row) use ($mappings, $suggestions) {
                $mapping = $mappings->get(strtoupper($row->office_code));

                return [
                    'pms_office_code' => $row->office_code,
                    'property_count' => (int) $row->property_count,
                    'mapping' => $mapping ? [
                        'id' => $mapping->id,
                        'office_id' => $mapping->office_id,
                        'office_code' => $mapping->office_code,
                        'office_name' => $mapping->office_name,
                        'updated_at' => $mapping->updated_at?->toIso8601String(),
                    ] : null,
                    'suggestion' => $suggestions[strtoupper($row->office_code)] ?? null,
                ];
            })
            ->values();

        return response()->json(['data' => $offices]);
    }

    public function upsert(Request $request, HrisClientService $hris)
    {
        $data = $request->validate([
            'pms_office_code' => ['required', 'string', 'max:255'],
            'office_id' => ['required', 'integer', 'min:1'],
            'office_code' => ['nullable', 'string', 'max:255'],
            'office_name' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(
            PmsProperty::where('office_code', $data['pms_office_code'])->exists(),
            422,
            "PMS has no office \"{$data['pms_office_code']}\".",
        );

        // Take the code and name from HRIS itself when it answers.
        try {
            $offices = collect($hris->getOfficesCached(minutes: 30));
        } catch (Throwable) {
            $offices = null;
        }

        if ($offices !== null && $offices->isNotEmpty()) {
            $office = $offices->first(fn ($o) => (int) ($o['id'] ?? 0) === (int) $data['office_id']);

            abort_unless($office, 422, 'That office is not in HRIS.');

            $data['office_code'] = $office['office_code'] ?? $data['office_code'] ?? null;
            $data['office_name'] = $office['office_desc'] ?? $data['office_name'] ?? null;
        }

        $mapping = PmsOfficeMapping::updateOrCreate(
            ['pms_office_code' => $data['pms_office_code']],
            [
                'office_id' => $data['office_id'],
                'office_code' => $data['office_code'] ?? null,
                'office_name' => $data['office_name'] ?? null,
                'updated_by_profile_id' => Auth::user()?->profile?->id,
            ],
        );

        return response()->json([
            'message' => "PMS \"{$mapping->pms_office_code}\" is {$mapping->office_code}.",
            'data' => $mapping,
        ]);
    }

    public function destroy(PmsOfficeMapping $mapping)
    {
        $mapping->delete();

        return response()->json(['message' => "PMS \"{$mapping->pms_office_code}\" is no longer mapped."]);
    }
}
