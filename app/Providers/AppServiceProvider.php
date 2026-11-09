<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\User;
use App\Observers\RequestObserver;
use App\Observers\TicketObserver;
use App\Services\ActivityLogger;
use App\Support\RequestTypes;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request: it collects that request's changes.
        $this->app->scoped(ActivityLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
        Ticket::observe(TicketObserver::class);

        // request_personnel stores the short type key ("design"), not the
        // class name. Not enforced: other morphs (notifications) keep
        // storing class names.
        Relation::morphMap(RequestTypes::morphMap());

        foreach (RequestTypes::morphMap() as $model) {
            $model::observe(RequestObserver::class);
        }

        $this->logSignIns();
    }

    /**
     * Every sign-in, sign-out and failed sign-in goes in the activity log
     * (docs/activity-log.md). A failed attempt records the username that
     * was tried -- never the password.
     */
    private function logSignIns(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                app(ActivityLogger::class)->record('login', $event->user, actor: $event->user);
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                app(ActivityLogger::class)->record('logout', $event->user, actor: $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            $attempted = collect($event->credentials)->except(['password'])->first();

            app(ActivityLogger::class)->record(
                'login_failed',
                $event->user instanceof User ? $event->user : null,
                context: ['username' => is_string($attempted) ? mb_substr($attempted, 0, 255) : null],
                label: is_string($attempted) ? mb_substr($attempted, 0, 255) : null,
            );
        });
    }
}
