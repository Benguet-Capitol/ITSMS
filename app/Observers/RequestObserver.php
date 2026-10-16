<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\RequestCreatedNotification;
use App\Services\ProfileEngagementService;
use Illuminate\Database\Eloquent\Model;

/**
 * Registered for every type in App\Support\RequestTypes (see
 * AppServiceProvider) -- the request counterpart of TicketObserver.
 */
class RequestObserver
{
    public function created(Model $request): void
    {
        $technicians = User::whereHas('roles', fn ($q) => $q->where('title', 'IT Technical'))->get();

        foreach ($technicians as $user) {
            $user->notify(new RequestCreatedNotification($request));
        }
    }

    public function updated(Model $request): void
    {
        if ($request->wasChanged('status')) {
            ProfileEngagementService::syncRequest($request);
        }
    }

    /**
     * request_personnel is a morph table with no foreign key back to the
     * request, so its rows have to be removed here -- and everyone who was
     * on the request re-synced, or they'd stay BUSY forever.
     */
    public function deleted(Model $request): void
    {
        $profiles = $request->personnel()->get();
        $request->personnel()->detach();

        foreach ($profiles as $profile) {
            ProfileEngagementService::sync($profile);
        }
    }
}
