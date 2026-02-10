<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'is_encrypted',
        'description',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get a setting value, decrypting if necessary.
     */
    public static function get(string $key, $default = null)
    {
        try {
            $setting = static::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            if ($setting->is_encrypted) {
                try {
                    return Crypt::decryptString($setting->value);
                } catch (\Exception $e) {
                    Log::error("Failed to decrypt setting {$key}: " . $e->getMessage());
                    return $default;
                }
            }

            return $setting->value;
        } catch (\Exception $e) {
            // Should fail gracefully if the table doesn't exist yet (e.g. during migration)
            return $default;
        }
    }

    /**
     * Set a setting value, encrypting if specified.
     */
    public static function set(string $key, $value, string $group = 'system', bool $encrypt = false, ?string $description = null)
    {
        $payload = [
            'value' => $encrypt ? Crypt::encryptString($value) : $value,
            'group' => $group,
            'is_encrypted' => $encrypt,
        ];

        if ($description) {
            $payload['description'] = $description;
        }

        static::updateOrCreate(
            ['key' => $key],
            $payload
        );
    }
}
