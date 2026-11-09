<?php

namespace App\Http\Resources;

use App\Http\Controllers\ActivityLogController;
use App\Models\ActivityLog;
use App\Support\RequestTypes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin ActivityLog */
class ActivityLogResource extends JsonResource
{
    /** subject type => the page it opens on */
    private const PATHS = [
        'ticket' => '/tickets',
        'inventory' => '/inventories',
        'user' => '/users',
        'role' => '/roles',
    ];

    public function toArray(Request $request): array
    {
        $subjectType = $this->subjectType();

        return [
            'id' => $this->id,
            'action' => $this->action,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'subject_type' => $subjectType,
            'subject_id' => $this->loggable_id,
            'subject_label' => $this->subject_label,
            'subject_path' => $this->subjectPath($subjectType),
            'changes' => $this->changes,
            'context' => $this->context,
            // Where from is for the people who audit the whole log.
            'ip_address' => $this->when(Gate::allows('activity_logs.view'), $this->ip_address),
            'user_agent' => $this->when(Gate::allows('activity_logs.view'), $this->user_agent),
            'created_at' => $this->created_at,
        ];
    }

    private function subjectType(): ?string
    {
        if (! $this->loggable_type) {
            return null;
        }

        foreach (ActivityLogController::subjectTypes() as $key => [$morphType]) {
            if ($morphType === $this->loggable_type) {
                return $key;
            }
        }

        return null;
    }

    private function subjectPath(?string $subjectType): ?string
    {
        if (! $subjectType || ! $this->loggable_id) {
            return null;
        }

        if (isset(self::PATHS[$subjectType])) {
            // Users and roles are managed from their list, not a page of their own.
            return in_array($subjectType, ['user', 'role'], true)
                ? self::PATHS[$subjectType]
                : self::PATHS[$subjectType].'/'.$this->loggable_id;
        }

        $config = RequestTypes::get($subjectType);

        return $config ? $config['path'].'/'.$this->loggable_id : null;
    }
}
