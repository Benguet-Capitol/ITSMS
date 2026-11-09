<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    // Without this trait, AuthenticatedSessionController::store() never
    // recognizes the user as 2FA-capable ($user instanceof
    // TwoFactorAuthenticatable is false), so login completes immediately
    // and the two-factor challenge never triggers — even for a user with
    // a confirmed 2FA secret. The setup endpoints (enable/confirm/
    // recovery codes) worked without this because those come straight
    // from Fortify's own controllers reading/writing the columns
    // directly; only the login-time check needs the trait.
    use TwoFactorAuthenticatable;

    protected $with = ['roles.permissions', 'profile.profileOffices', 'profile.agencies'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (! $model->id) {
                // Cast to string: Str::uuid() returns a Ramsey\Uuid\UuidInterface
                // object, so an in-memory instance (e.g. right after create())
                // would hold an object here while a DB-refetched instance holds
                // a plain string — identical value, different type, which trips
                // strict (===) comparisons like assertAuthenticatedAs() in tests.
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function activityLabel(): string
    {
        return (string) $this->username;
    }

    /**
     * The account, its role and its profile (LogsActivity). The password
     * is compared by hash and only ever shown as "changed"; presence
     * fields (status, last seen) aren't tracked.
     */
    public function activitySnapshot(): array
    {
        $profile = $this->profile()->with(['agencies', 'profileOffices'])->first();

        return [
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->status,
            'password' => $this->password,
            'roles' => $this->roles()->pluck('title')->sort()->values()->all(),
            'display_name' => $profile?->display_name,
            'designation' => $profile?->designation,
            'gender' => $profile?->gender,
            'offices_assigned' => $profile?->profileOffices->map(fn ($office) => $office->office_desc ?: $office->office_code)->sort()->values()->all() ?? [],
            'agencies_assigned' => $profile?->agencies->pluck('name')->sort()->values()->all() ?? [],
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
