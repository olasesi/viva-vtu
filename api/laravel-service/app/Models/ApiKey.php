<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'prefix',
        'key',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function issue(int $userId, string $name = 'Reseller API'): array
    {
        $raw = 'viva_'.strtolower(Str::random(28));

        $model = static::create([
            'user_id' => $userId,
            'name' => $name,
            'prefix' => substr($raw, 0, 10),
            'key' => hash('sha256', $raw),
        ]);

        return [
            'api_key' => $raw,
            'api_key_id' => $model->id,
            'prefix' => $model->prefix,
        ];
    }

    public function tokenMatches(string $raw): bool
    {
        return hash_equals($this->key, hash('sha256', $raw));
    }
}
