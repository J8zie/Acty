<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Support\ImageStore;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person with an account: a resident, a volunteer or an admin (see App\Enums\Role).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'username', 'first_name', 'last_name', 'email', 'password', 'role', 'photo_path',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            // 'hashed' = assigning a plain password stores its bcrypt hash; assigning a hash leaves it alone.
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isVolunteer(): bool
    {
        return $this->role === Role::Volunteer;
    }

    public function isResident(): bool
    {
        return $this->role === Role::Resident;
    }

    /** Reports this person filed. */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** Tasks this person took as a volunteer. */
    public function tasks(): HasMany
    {
        return $this->hasMany(ReportTask::class, 'volunteer_id');
    }

    /**
     * The address of the profile picture, or null (the app then draws the initials).
     * The ?v= part changes whenever the account is saved, so a browser never shows a stale picture.
     */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return app(ImageStore::class)->url($this->photo_path, 'public').'?v='.($this->updated_at?->timestamp ?? 0);
    }
}
