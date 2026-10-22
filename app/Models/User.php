<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Organization\Models\Branch;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->withPivot('is_granted')
            ->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles()
            ->where('code', $roleCode)
            ->where('is_active', true)
            ->exists();
    }

    public function hasPermission(string $permissionCode): bool
    {
        $override = $this->permissions()
            ->where('code', $permissionCode)
            ->first();

        if ($override !== null) {
            return (bool) $override->pivot->is_granted;
        }

        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas('permissions', fn ($query) => $query->where('code', $permissionCode))
            ->exists();
    }

    public function canAccessBranch(Branch $branch): bool
    {
        return $this->hasRole('super_admin') || $this->branches()->whereKey($branch->getKey())->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
