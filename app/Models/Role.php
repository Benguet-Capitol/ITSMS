<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use LogsActivity;

    protected $fillable = [
        'title',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function activityLabel(): string
    {
        return (string) $this->title;
    }

    /** The role's name and every permission it grants (LogsActivity). */
    public function activitySnapshot(): array
    {
        return [
            'title' => $this->title,
            'permissions' => $this->permissions()->pluck('title')->sort()->values()->all(),
        ];
    }
}
