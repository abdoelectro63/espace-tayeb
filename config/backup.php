<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

return [

    'backup' => [
        /*
         * اسم التطبيق المستخدم في مراقبة النسخ الاحتياطي.
         */
        'name' => env('APP_NAME', 'Espace-Tayeb-Backup'),

        'source' => [
            'files' => [
                /*
                 * المجلدات والملفات التي سيتم تضمينها. 
                 * قمنا بتضمين base_path لضمان نسخ الكود بالكامل.
                 */
                'include' => [
                    base_path(),
                ],

                /*
                 * المجلدات المستثناة لتقليل حجم ملف الـ Zip وتجنب الملفات المؤقتة.
                 */
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('framework'),
                    storage_path('logs'),
                    storage_path('app/backup-temp'),
                ],

                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            /*
             * قواعد البيانات التي سيتم نسخها (MySQL هي الافتراضية لـ Laravel).
             */
            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        /*
         * على Windows بدون gzip في PATH، نتركه null لتفادي فشل dump.
         */
        'database_dump_compressor' => null,

        'database_dump_file_timestamp_format' => 'Y-m-d-H-i-s',
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => 'sql',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => 'EspaceTayeb_',

            /*
             * الأقراص التي سيتم حفظ النسخ عليها. 
             * 'local' يحفظها في السيرفر، ويفضل مستقبلاً إضافة 's3' أو 'google_drive'.
             */
            'disks' => [
                'local',
            ],

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * تشفير الملف بكلمة سر (يجب ضبط BACKUP_ARCHIVE_PASSWORD في الـ .env).
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',

        'verify_backup' => true, // التأكد من سلامة الملف بعد الضغط
        'tries' => 3,
        'retry_delay' => 2,
    ],

    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => ['mail'],
            HealthyBackupWasFoundNotification::class => ['mail'],
            CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'your-email@example.com'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@espace-tayeb.com'),
                'name' => env('APP_NAME', 'Espace Tayeb Admin'),
            ],
        ],

        // تأكد من وجود هذه المفاتيح حتى لو كانت فارغة لتجنب ErrorException
        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'Espace-Tayeb-Backup'),
            'disks' => ['local'],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,      // احتفظ بكل النسخ لآخر 7 أيام
            'keep_daily_backups_for_days' => 16,   // ثم نسخة واحدة يومية لمدة 16 يوم
            'keep_weekly_backups_for_weeks' => 8,  // ثم نسخة أسبوعية لمدة شهرين
            'keep_monthly_backups_for_months' => 4, // ثم نسخة شهرية لـ 4 أشهر
            'keep_yearly_backups_for_years' => 2,  // ثم نسخة سنوية لعامين
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000, // حذف الأقدم إذا تجاوز الحجم 5GB
        ],
    ],
];