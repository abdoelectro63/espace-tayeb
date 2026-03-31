<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('footer.copyright_text', '');
        $this->migrator->add('footer.social_links', []);
    }
};
