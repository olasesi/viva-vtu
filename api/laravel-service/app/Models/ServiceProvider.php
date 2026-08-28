<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'api_key',
        'auth_string',
        'is_active',
        'config',
    ];

    protected $casts = [
        'config' => 'json',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getAuthHeaderAttribute(): string
    {
        return $this->auth_string ?? '';
    }
}
