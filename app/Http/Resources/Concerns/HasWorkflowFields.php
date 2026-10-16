<?php

namespace App\Http\Resources\Concerns;

use App\Http\Resources\RequestPersonnelResource;
use Illuminate\Http\Request;

/**
 * Workflow fields every request-type resource returns
 * (docs/adr/0002-request-workflow.md). The resource's model must use
 * HasRequestWorkflow; `abilities` is only sent when personnel is loaded.
 */
trait HasWorkflowFields
{
    /** @return array<string, mixed> */
    protected function workflowFields(Request $request): array
    {
        return [
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'personnel' => RequestPersonnelResource::collection($this->whenLoaded('personnel')),
            'accepted_at' => $this->accepted_at,
            'finished_at' => $this->finished_at,
            'completed_at' => $this->completed_at,
            'reopened_at' => $this->reopened_at,
            'cancellation_reason' => $this->cancellation_reason,
            // The requestor's feedback name is always this (HasRequestWorkflow).
            'requester' => $this->requesterName(),
            'abilities' => $this->when(
                $this->relationLoaded('personnel'),
                fn () => $this->workflowAbilities($request->user()),
            ),
        ];
    }
}
