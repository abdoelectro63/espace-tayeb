<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('tracking.facebook_pixel_id', '');
        $this->migrator->add('tracking.facebook_access_token', '');
        $this->migrator->add('tracking.facebook_test_event_code', '');
        $this->migrator->add('tracking.tiktok_pixel_id', '');
        $this->migrator->add('tracking.tiktok_access_token', '');
        $this->migrator->add('tracking.tracking_debug', false);
    }
};
