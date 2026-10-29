<?php

namespace App\Models\Concerns;

use App\Enums\RequestStatus;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Ticket-style accept/assign workflow for a request type (see
 * docs/adr/0002-request-workflow.md). The model's table needs the
 * workflow columns (status, accepted_at, finished_at, completed_at,
 * reopened_at, cancellation_reason) and the type must be registered in
 * App\Support\RequestTypes. None of these columns are fillable -- they
 * only change through RequestWorkflowController.
 */
trait HasRequestWorkflow
{
    /** Permission each workflow action needs, on top of requests.<type>.view. */
    public const WORKFLOW_PERMISSIONS = [
        'accept' => 'requests.accept',
        'unaccept' => 'requests.accept',
        'hold' => 'requests.accept',
        'resume' => 'requests.accept',
        'finish' => 'requests.accept',
        'assign' => 'requests.assign',
        'remove_personnel' => 'requests.assign',
        'complete' => 'requests.complete',
        'cancel' => 'requests.cancel',
        'reopen' => 'requests.reopen',
    ];

    /**
     * The requestor's feedback is given by the requester, so its name is
     * always the requester's name (the model's REQUESTER_FIELD), set once a
     * rating exists.
     */
    public static function bootHasRequestWorkflow(): void
    {
        static::saving(function (self $request) {
            if (! static::hasFeedback()) {
                return;
            }

            $request->feedback_name = $request->feedback_rating !== null
                ? $request->requesterName()
                : null;
        });
    }

    /**
     * Whether the type's form has the requestor's feedback (rating)
     * section. A type without one sets HAS_FEEDBACK = false and needs no
     * feedback columns or REQUESTER_FIELD; an admin then completes it
     * without a rating.
     */
    public static function hasFeedback(): bool
    {
        return ! defined(static::class.'::HAS_FEEDBACK') || static::HAS_FEEDBACK;
    }

    /** The requester's name, from the type's own requester column. */
    public function requesterName(): ?string
    {
        return static::hasFeedback() ? $this->{static::REQUESTER_FIELD} : null;
    }

    public function initializeHasRequestWorkflow(): void
    {
        $this->mergeCasts([
            'status' => RequestStatus::class,
            'accepted_at' => 'datetime',
            'finished_at' => 'datetime',
            'completed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ]);
    }

    public function personnel(): MorphToMany
    {
        return $this->morphToMany(Profile::class, 'requestable', 'request_personnel')
            ->withPivot('assigned_by_profile_id')
            ->withTimestamps();
    }

    public function scopeAssignedTo(Builder $query, int $profileId): Builder
    {
        return $query->whereHas('personnel', fn ($q) => $q->where('profiles.id', $profileId));
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->doesntHave('personnel')
            ->whereNotIn('status', [RequestStatus::Completed, RequestStatus::Cancelled]);
    }

    /** List tab: "mine" (requests the profile is on), "unassigned", or all. */
    public function scopeForTab(Builder $query, ?string $tab, ?Profile $profile): Builder
    {
        return match ($tab) {
            'mine' => $query->assignedTo((int) $profile?->id),
            'unassigned' => $query->unassigned(),
            default => $query,
        };
    }

    /** Validation for the list's `tab` and `status` query params. */
    public static function listFilterRules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(RequestStatus::values())],
            'tab' => ['nullable', 'string', Rule::in(['all', 'mine', 'unassigned'])],
        ];
    }

    /**
     * Fields the admin must fill when completing: the form's requestor
     * feedback section (its name is the requester's, filled in
     * automatically). Override on a type whose form differs.
     *
     * @return array<string, array<mixed>>
     */
    public function completionRules(): array
    {
        if (! static::hasFeedback()) {
            return [];
        }

        return [
            'feedback_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_date' => ['required', 'date'],
        ];
    }

    /**
     * Form fields that must be filled before the request can be marked
     * for completion, as field => message for each one still missing.
     * Override on a type whose form has a "work done" section.
     *
     * @return array<string, string>
     */
    public function finishRequirements(): array
    {
        return [];
    }

    /** How notifications refer to the request: its control number, or "#id" when it has none. */
    public function workflowReference(): string
    {
        return $this->control_number ?: "#{$this->getKey()}";
    }

    /** The person's own personnel row, if they're on this request. */
    public function personnelEntry(?Profile $profile): ?Profile
    {
        if (! $profile) {
            return null;
        }

        // Lists eager-load personnel; don't re-query it for every action.
        return $this->relationLoaded('personnel')
            ? $this->personnel->firstWhere('id', $profile->id)
            : $this->personnel()->where('profiles.id', $profile->id)->first();
    }

    /**
     * Whether the request's current status (and the person's place on it)
     * allows the action. Permissions are checked separately, so the
     * controller can tell "not allowed" (403) from "not now" (422).
     */
    public function workflowAllows(string $action, ?Profile $profile, bool $canAssign = false): bool
    {
        $status = $this->status;
        $entry = $this->personnelEntry($profile);
        $isMine = $entry !== null;
        // Only people who joined themselves may leave on their own; anyone
        // an admin put on the request is removed by an admin.
        $assignedBy = $isMine ? $entry->pivot->assigned_by_profile_id : null;
        $joinedThemselves = $isMine && ($assignedBy === null || (int) $assignedBy === $profile->id);
        $isWorkingIt = $isMine || $canAssign;

        return match ($action) {
            'accept' => ! $isMine && in_array($status, [RequestStatus::Pending, RequestStatus::InProgress, RequestStatus::OnHold], true),
            'unaccept' => $joinedThemselves && in_array($status, [RequestStatus::InProgress, RequestStatus::OnHold], true),
            'assign' => in_array($status, [RequestStatus::Pending, RequestStatus::InProgress, RequestStatus::OnHold], true),
            'remove_personnel' => ! $status->isClosed() && $status !== RequestStatus::ForCompletion,
            'hold', 'finish' => $isWorkingIt && $status === RequestStatus::InProgress,
            'resume' => $isWorkingIt && in_array($status, [RequestStatus::OnHold, RequestStatus::ForCompletion], true),
            'complete' => $status === RequestStatus::ForCompletion,
            'cancel' => ! $status->isClosed(),
            'reopen' => $status->isClosed(),
            default => false,
        };
    }

    /**
     * Which workflow actions the user may take right now -- the frontend
     * builds the row menu from this rather than duplicating the rules.
     *
     * @return array<string, bool>
     */
    public function workflowAbilities(?User $user): array
    {
        $profile = $user?->profile;
        $canAssign = Gate::forUser($user)->allows('requests.assign');

        return collect(self::WORKFLOW_PERMISSIONS)
            ->mapWithKeys(fn (string $permission, string $action) => [
                $action => $user !== null
                    && Gate::forUser($user)->allows($permission)
                    && $this->workflowAllows($action, $profile, $canAssign),
            ])
            ->all();
    }
}
