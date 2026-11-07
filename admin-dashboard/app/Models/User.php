<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
            'admin_permissions' => 'array',
        ];
    }

    public function canManage(string $module): bool
    {
        return $this->role === 'admin' && $this->status === 'active' && ! $this->trashed()
            && ($this->admin_permissions === null || in_array($module, $this->admin_permissions, true));
    }

    public function workspaceHome(): string
    {
        foreach (['reports' => 'dashboard', 'catalog' => 'products', 'inventory' => 'inventory', 'orders' => 'orders', 'customers' => 'customers', 'payments' => 'payments', 'promotions' => 'management.index', 'shipping' => 'management.index', 'reviews' => 'management.index', 'administration' => 'staff'] as $module => $route) {
            if ($this->canManage($module)) {
                return $route === 'management.index' ? route($route, match ($module) {
                    'promotions' => 'coupons','shipping' => 'shipping-methods',default => 'reviews'
                }) : route($route);
            }
        }

        return route('settings');
    }
}
