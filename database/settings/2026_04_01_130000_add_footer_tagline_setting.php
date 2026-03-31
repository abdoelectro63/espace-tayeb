<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add(
            'footer.tagline',
            'متجركم للأجهزة المنزلية والمنتجات المختارة — جودة، شفافية في الأسعار، وخدمة قريبة منكم.'
        );
    }

    public function down(): void
    {
        $this->migrator->delete('footer.tagline');
    }
};
