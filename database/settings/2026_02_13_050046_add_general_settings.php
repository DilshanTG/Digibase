<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('general', function (\Spatie\LaravelSettings\Migrations\SettingsBlueprint $blueprint) {
            $blueprint->add('storage_driver', 'local');
            $blueprint->add('aws_access_key_id', null);
            $blueprint->add('aws_secret_access_key', null);
            $blueprint->add('aws_default_region', 'us-east-1');
            $blueprint->add('aws_bucket', null);
            $blueprint->add('aws_endpoint', null);
            $blueprint->add('aws_use_path_style', 'false');
            $blueprint->add('aws_url', null);
        });
    }
};
