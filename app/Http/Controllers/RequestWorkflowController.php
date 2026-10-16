<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Notifications\RequestAssignedNotification;
use App\Notifications\RequestPersonnelJoinedNotification;
use App\Services\ProfileEngagementService;
use App\Support\RequestTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Accept/assign workflow shared by every request type in RequestTypes
 * (docs/adr/0002-request-workflow.md). Routes look like
 * POST /api/requests/{type}/{id}/{action}; each action needs the type's
 * requests.<type>.view permission plus the action's own permission, and
 * the request's status must allow it (HasRequestWorkflow::workflowAllows).
 */
class RequestWorkflowController extends Controller
{
    public function accept(string $type, int $id)
    {
        [$record, $profile] = $this->resolve($type, $id, 'accept');

        $others = $record->personnel()->get();

        DB::transaction(function () use ($record, $profile) {
            $record->personnel()->attach($profile->id, ['assigned_by_profile_id' => null]);
            $this->markAccepted($record);
        });

        ProfileEngagementService::sync($profile);

        foreach ($others as $other) {
            $other->user?->notify(new RequestPersonnelJoinedNotification($record, $profile));
        }

        return $this->respond($type, $record);
    }

    public function unaccept(string $type, int $id)
    {
        [$record, $profile] = $this->resolve($type, $id, 'unaccept');

        $this->removePersonnelFrom($record, $profile);

        return $this->respond($type, $record);
    }

    public function assign(Request $request, string $type, int $id)
    {
        [$record, $admin] = $this->resolve($type, $id, 'assign');

        $data = $request->validate([
            'profile_ids' => ['required', 'array', 'min:1'],
            'profile_ids.*' => [
                'integer',
                'distinct',
                Rule::in($this->assignableProfiles()->pluck('id')->all()),
            ],
        ], [
            'profile_ids.required' => 'Select at least one person to assign.',
            'profile_ids.*.in' => 'Only IT personnel who can accept requests can be assigned.',
        ]);

        $alreadyOn = $record->personnel()->pluck('profiles.id')->all();
        $newIds = array_values(array_diff($data['profile_ids'], $alreadyOn));

        DB::transaction(function () use ($record, $admin, $newIds) {
            $record->personnel()->attach(
                array_fill_keys($newIds, ['assigned_by_profile_id' => $admin->id]),
            );

            if ($newIds) {
                $this->markAccepted($record);
            }
        });

        foreach (Profile::with('user')->whereIn('id', $newIds)->get() as $assignee) {
            ProfileEngagementService::sync($assignee);

            if ($assignee->id !== $admin->id) {
                $assignee->user?->notify(new RequestAssignedNotification($record, $admin));
            }
        }

        return $this->respond($type, $record);
    }

    public function removePersonnel(string $type, int $id, int $profileId)
    {
        [$record] = $this->resolve($type, $id, 'remove_personnel');

        $profile = $record->personnel()->where('profiles.id', $profileId)->first();
        abort_unless($profile, 404, 'That person is not on this request.');

        $this->removePersonnelFrom($record, $profile);

        return $this->respond($type, $record);
    }

    public function hold(string $type, int $id)
    {
        [$record] = $this->resolve($type, $id, 'hold');

        $record->forceFill(['status' => RequestStatus::OnHold])->save();

        return $this->respond($type, $record);
    }

    /** Back to work from on hold, or from for-completion when the client's rating turns up more work. */
    public function resume(string $type, int $id)
    {
        [$record] = $this->resolve($type, $id, 'resume');

        $record->forceFill([
            'status' => RequestStatus::InProgress,
            'finished_at' => null,
        ])->save();

        return $this->respond($type, $record);
    }

    /** Work done; waiting for the client's rating before an admin completes it. */
    public function finish(string $type, int $id)
    {
        [$record] = $this->resolve($type, $id, 'finish');

        $missing = $record->finishRequirements();
        if ($missing) {
            throw ValidationException::withMessages($missing);
        }

        $record->forceFill([
            'status' => RequestStatus::ForCompletion,
            'finished_at' => now(),
        ])->save();

        return $this->respond($type, $record);
    }

    public function complete(Request $request, string $type, int $id)
    {
        [$record] = $this->resolve($type, $id, 'complete');

        $feedback = $request->validate($record->completionRules(), [
            'feedback_rating.required' => "Enter the client's rating before completing.",
            'feedback_date.required' => 'Enter the date of the rating.',
        ]);

        $record->forceFill([
            ...$feedback,
            'status' => RequestStatus::Completed,
            'completed_at' => now(),
        ])->save();

        return $this->respond($type, $record);
    }

    public function cancel(Request $request, string $type, int $id)
    {
        [$record] = $this->resolve($type, $id, 'cancel');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'A cancellation reason is required.',
        ]);

        $record->forceFill([
            'status' => RequestStatus::Cancelled,
            'cancellation_reason' => trim($data['reason']),
        ])->save();

        return $this->respond($type, $record);
    }

    /** Back to in progress with the same people, or to pending if nobody is left on it. */
    public function reopen(string $type, int $id)
    {
        [$record] = $this->resolve($type, $id, 'reopen');

        $hasPersonnel = $record->personnel()->exists();

        $record->forceFill([
            'status' => $hasPersonnel ? RequestStatus::InProgress : RequestStatus::Pending,
            'accepted_at' => $hasPersonnel ? $record->accepted_at : null,
            'finished_at' => null,
            'completed_at' => null,
            'reopened_at' => now(),
            'cancellation_reason' => null,
        ])->save();

        return $this->respond($type, $record);
    }

    /** IT personnel an admin can assign: everyone whose roles grant requests.accept. */
    public function personnelOptions()
    {
        Gate::authorize('requests.assign');

        return ProfileResource::collection(
            $this->assignableProfiles()->orderBy('display_name')->get(),
        );
    }

    private function assignableProfiles()
    {
        return Profile::query()->whereHas(
            'user.roles.permissions',
            fn ($q) => $q->where('title', 'requests.accept'),
        );
    }

    /**
     * Finds the request and checks, in order: the type's view permission
     * and the action's permission (403), then whether the request's status
     * allows the action right now (422).
     *
     * @return array{0: Model, 1: Profile}
     */
    private function resolve(string $type, int $id, string $action): array
    {
        $config = RequestTypes::get($type);
        abort_unless($config, 404);

        $model = $config['model'];

        Gate::authorize("{$config['permission']}.view");
        Gate::authorize($model::WORKFLOW_PERMISSIONS[$action]);

        $record = $model::query()->findOrFail($id);
        $profile = Auth::user()->profile;
        abort_unless($profile, 404, 'Profile not found.');

        if (! $record->workflowAllows($action, $profile, Gate::allows('requests.assign'))) {
            abort(422, $this->notAllowedMessage($action, $record, $profile));
        }

        return [$record, $profile];
    }

    private function notAllowedMessage(string $action, Model $record, Profile $profile): string
    {
        $status = $record->status->label();

        return match ($action) {
            'accept' => $record->status->isClosed() || $record->status === RequestStatus::ForCompletion
                ? "A request that is {$status} can't be accepted."
                : 'You are already on this request.',
            'unaccept' => match (true) {
                $record->personnelEntry($profile) === null => 'You are not on this request.',
                in_array($record->status, [RequestStatus::InProgress, RequestStatus::OnHold], true) => 'An admin assigned you to this request, so only an admin can take you off it.',
                default => "You can't leave a request that is {$status}.",
            },
            'hold', 'finish', 'resume' => "Only people on this request can do that, and not while it is {$status}.",
            'complete' => 'A request can only be completed once it is marked For Completion.',
            'reopen' => 'Only completed or cancelled requests can be reopened.',
            default => "This can't be done while the request is {$status}.",
        };
    }

    /** First person on a pending request moves it to in progress -- what prints as "Date Received". */
    private function markAccepted(Model $record): void
    {
        if ($record->status === RequestStatus::Pending) {
            $record->forceFill([
                'status' => RequestStatus::InProgress,
                'accepted_at' => now(),
            ])->save();
        }
    }

    /** Last person leaving an in-progress request puts it back to pending, as with tickets. */
    private function removePersonnelFrom(Model $record, Profile $profile): void
    {
        DB::transaction(function () use ($record, $profile) {
            $record->personnel()->detach($profile->id);

            if ($record->status === RequestStatus::InProgress && ! $record->personnel()->exists()) {
                $record->forceFill([
                    'status' => RequestStatus::Pending,
                    'accepted_at' => null,
                ])->save();
            }
        });

        ProfileEngagementService::sync($profile);
    }

    private function respond(string $type, Model $record)
    {
        $resource = RequestTypes::get($type)['resource'];

        return new $resource($record->refresh()->load('personnel'));
    }
}
