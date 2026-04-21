<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;

class AuthGates
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request?->user()?->loadMissing('roles.permissions');
        $permissions = [];

        if($user) {
          foreach($user->roles as $role) {
            foreach($role->permissions as $singlePermission) {
              $permissions[] = $singlePermission->title;
            }
          }

          collect($permissions)->unique()->each(function ($permission) {
            Gate::define($permission, function ($user) {
              return true;
            });
          });
        }
        return $next($request);
    }
}
