<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public ?string $storage_driver = 'local';
    public ?string $aws_access_key_id = null;
    public ?string $aws_secret_access_key = null;
    public ?string $aws_default_region = 'us-east-1';
    public ?string $aws_bucket = null;
    public ?string $aws_endpoint = null;
    public ?string $aws_use_path_style = 'false';
    public ?string $aws_url = null;

    public static function group(): string
    {
        return 'general';
    }
}
