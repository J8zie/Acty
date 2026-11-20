<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Support\ImageStore;
use Laravel\Sanctum\HasApiTokens;

// A person who can sign in: resident, volunteer or admin.
class User extends Authenticatable
{

    use HasApiTokens, HasFactory;

    protected $fillable = [
        'username', 'first_name', 'last_name', 'email', 'password', 'role', 'photo_path',
    ];

    protected $hidden = ['password'];

    // Tells Laravel which fields get special treatment.
    protected function casts(): array
    {
        return [

            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    // Checks if this user is an admin.
    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    // Checks if this user is a volunteer.
    public function isVolunteer(): bool
    {
        return $this->role === Role::Volunteer;
    }

    // Checks if this user is a resident.
    public function isResident(): bool
    {
        return $this->role === Role::Resident;
    }


    // The reports this user filed.
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }


    // The tasks this volunteer took.
    public function tasks(): HasMany
    {
        return $this->hasMany(ReportTask::class, 'volunteer_id');
    }


    // The web address of this user's profile photo.
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return app(ImageStore::class)->url($this->photo_path, 'public').'?v='.($this->updated_at?->timestamp ?? 0);
    }
}
