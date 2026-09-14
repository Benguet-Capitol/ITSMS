<?php

namespace App\Http\Resources;

use App\Enums\ServiceMethod;
use App\Enums\TicketStatus;
use App\Models\ProfileOffice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profileId = Auth::user()->profile->id;

        $inventory = $this->whenLoaded('inventory');
        $agency = $this->whenLoaded('agency');

        $employeeMap = $request->attributes->get('employeeMap');
        $inventoryEmployee = $employeeMap?->get((int) $inventory?->employee_id) ?? $employeeMap?->get((int) $inventory?->parent_component?->employee_id);

        $officeId =
          $this->office_id ??
          data_get($inventoryEmployee, 'office_id') ??
          data_get($inventoryEmployee, 'office.id') ??
          data_get($inventoryEmployee, 'office.office_id') ??
          data_get($inventoryEmployee, 'officeId') ??
          data_get($inventoryEmployee, 'department_id') ??
          data_get($inventoryEmployee, 'department.id');

        $personnelOfficeAssigned = collect();

        if ($officeId) {
            $personnelOfficeAssigned = ProfileOffice::query()
                ->with('profile')
                ->where('office_id', (string) $officeId)
                ->get()
                ->pluck('profile')
                ->filter()
                ->values();
        }

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'profile' => $this->profile ? new ProfileResource($this->profile) : null,
            'inventory' => InventoryResource::make($inventory),
            'item_type' => $this->item_type ? new ItemTypeResource($this->item_type) : null,
            'it_service' => $this->itService ? new ItServiceResource($this->itService) : null,
            'personnel' => ProfileResource::collection($this->whenLoaded('personnel')),
            'solution' => SolutionResource::make($this->whenLoaded('solution')),
            'agency' => AgencyResource::make($agency),
            'personnel_agency_assigned' => ProfileResource::collection($agency?->assigned_profiles ?? collect()),
            'personnel_office_assigned' => ProfileResource::collection($personnelOfficeAssigned),

            'ticket_number' => $this->ticket_number,
            'concern' => $this->concern,
            'query_status' => $this->query_status,
            'request_status' => $this->request_status,
            'complexity_level' => $this->complexityLevel ? new TicketComplexityLevelResource($this->complexityLevel) : null,
            'service_method' => $this->service_method,
            'service_method_formatted' => match ($this->service_method) {
                ServiceMethod::OnSite => 'On site',
                ServiceMethod::PulledOut => 'Pulled out',
                default => null,
            },
            'date' => $this->date,
            'accepted_at' => $this->accepted_at,
            'resolved_at' => $this->resolved_at,
            'released_at' => $this->released_at,
            'released_by' => $this->released_by,
            'contact_number' => $this->contact_number,
            'full_name' => $this->full_name,
            'client_name' => $this->client_name,
            'is_other_agency' => $this->is_other_agency,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // engagement info
            'is_accepted_by_me' => (bool) ($this->accepted_by_me ?? 0), // from withCount
            'personnel_count' => $this->personnel_count ?? $this->personnel?->count() ?? 0,
            'is_accepted_by_others' => ($this->personnel_count ?? 0) > 0
                && ! ($this->accepted_by_me ?? false),

            // derived flags (frontend-friendly)
            'is_open' => $this->request_status === TicketStatus::Open,
            'is_closed' => in_array($this->query_status, [
                TicketStatus::Resolved,
                TicketStatus::Assessed,
                TicketStatus::Cancelled,
            ]),
            'is_in_progress' => $this->query_status === TicketStatus::InProgress,

            // logic for acceptance
            'can_accept' => in_array($this->query_status, [
                TicketStatus::Queued,
                TicketStatus::InProgress,
                TicketStatus::CheckingStock,
                TicketStatus::AwaitingPart,
                TicketStatus::AwaitingUser,
                TicketStatus::AwaitingVendor,
            ]) && ! ($this->accepted_by_me ?? false),

            'can_unaccept' => ($this->accepted_by_me ?? false) && ! in_array($this->query_status, [
                TicketStatus::Resolved,
                TicketStatus::Assessed,
                TicketStatus::Cancelled,
            ]),

            'assessment' => $this->whenLoaded('assessment', fn () => [
                'control_number' => $this->assessment->control_number,
                'findings' => $this->assessment->findings,
                'recommendations' => $this->assessment->recommendations,
                'replacement_available' => $this->assessment->replacement_available,
                'specifications' => $this->assessment->specifications,
                'acquisition_cost' => $this->assessment->acquisition_cost,
                'is_set' => $this->assessment->is_set,
                'components' => $this->assessment->components,
                'component_remarks' => $this->assessment->component_remarks,
                'reviewed_by' => $this->assessment->reviewed_by,
                'reviewed_by_position' => $this->assessment->reviewed_by_position,
                'assessed_by' => $this->assessment->assessed_by,
                'assessed_by_position' => $this->assessment->assessed_by_position,
                'created_at' => $this->assessment->created_at,
            ]),

            'office_id' => $this->office_id,
            'office_code' => $this->office_code,
            'office_desc' => $this->office_desc,
        ];
    }
}
