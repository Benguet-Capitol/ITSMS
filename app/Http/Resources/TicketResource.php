<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Enums\ServiceMethod;
use Illuminate\Http\Request;
use App\Http\Resources\InventoryResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\ItServiceResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\SolutionResource;
use App\Http\Resources\ItemTypeResource;
use App\Http\Resources\AgencyResource;

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

        return [
          'id' => $this->id,
          'profile' => ProfileResource::make($this->whenLoaded('profile')),
          'inventory' => InventoryResource::make($inventory),
          'item_type' => ItemTypeResource::make($this->whenLoaded('item_type')),
          'it_service' => ItServiceResource::make($this->whenLoaded('itService')),
          'personnel' => ProfileResource::collection($this->whenLoaded('personnel')),
          'solution' => SolutionResource::make($this->whenLoaded('solution')),
          'agency' => AgencyResource::make($agency),
          'personnel_agency_assigned' => ProfileResource::collection($agency?->assigned_profiles ?? collect()),
          'personnel_office_assigned' => ProfileResource::collection($inventory?->employee?->department?->assigned_profiles ?? collect()),

          // core ticket fields
          'ticket_number' => $this->ticket_number,
          'concern' => $this->concern,
          'query_status' => $this->query_status,
          'request_status' => $this->request_status,
          'priority' => $this->priority,
          'service_method' => $this->service_method,
          'service_method_formatted' => match ($this->service_method) {
              ServiceMethod::OnSite => 'On site',
              ServiceMethod::PulledOut => 'Pulled out',
              default => null,
          },
          'date' => $this->date,
          'released_at' => $this->released_at,
          'contact_number' => $this->contact_number,
          'full_name' => $this->full_name,
          'is_other_agency' => $this->is_other_agency,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,

          // engagement info
          'is_accepted_by_me' => (bool) ($this->accepted_by_me ?? 0), // from withCount
          'personnel_count' => $this->personnel_count ?? $this->personnel?->count() ?? 0,
          'is_accepted_by_others' => ($this->personnel_count ?? 0) > 0
              && !($this->accepted_by_me ?? false),

          // derived flags (frontend-friendly)
          'is_open' => $this->request_status === TicketStatus::Open,
          'is_closed' => in_array($this->query_status, [
              TicketStatus::Resolved,
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
              TicketStatus::AwaitingVendor
          ]) && !($this->accepted_by_me ?? false),
        ];
    }
}
