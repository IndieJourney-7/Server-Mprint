<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function favoriteTemplates()
    {
        return $this->belongsToMany(Template::class, 'user_favorite_templates')
            ->withTimestamps();
    }

    public function designs()
    {
        return $this->hasMany(UserDesign::class);
    }

    public function uploads()
    {
        return $this->hasMany(UserUpload::class);
    }
}
