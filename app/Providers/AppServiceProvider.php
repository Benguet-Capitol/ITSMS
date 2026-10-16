<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Observers\RequestObserver;
use App\Observers\TicketObserver;
use App\Support\RequestTypes;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
