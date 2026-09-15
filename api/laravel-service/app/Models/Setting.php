<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeForKey($query, string $key)
    {
        return $query->where('key', $key);
    }
}
