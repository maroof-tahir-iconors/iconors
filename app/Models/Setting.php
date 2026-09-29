<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class Setting extends Model
{
    protected $fillable = [
        'group', 'key', 'value', 'type', 'is_encrypted', 'is_public',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_public'    => 'boolean',
    ];

    /**
     * Decrypt on read when flagged. A DecryptException means the row was
     * written under a different APP_KEY — return null rather than throwing,
     * so one rotated key cannot take the whole site down. The admin UI
     * surfaces the empty credential and prompts a re-entry.
     */
    public function getValueAttribute($value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->is_encrypted) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException) {
                return null;
            }
        }

        return $this->castValue($value);
    }

    public function setValueAttribute($value): void
    {
        if (is_array($value)) {
            $value = json_encode($value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $this->attributes['value'] = $this->is_encrypted && $value !== null
            ? Crypt::encryptString((string) $value)
            : $value;
    }

    protected function castValue(string $value): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float'   => (float) $value,
            'json'    => json_decode($value, true) ?? [],
            default   => $value,
        };
    }
}
