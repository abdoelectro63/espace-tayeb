<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Spatie media saved on the "local" disk lives under storage/app/private, but /storage/ URLs
 * only serve storage/app/public. This copies files and updates the media row to disk=public.
 */
class MigrateMediaToPublicDisk extends Command
{
    protected $signature = 'media:migrate-to-public-disk
                            {--dry-run : List actions without copying or updating}';

    protected $description = 'Move Spatie media files from the local (private) disk to the public disk';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $local = Storage::disk('local');
        $public = Storage::disk('public');

        $query = Media::query()->where('disk', 'local');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No media rows use disk "local".');

            return self::SUCCESS;
        }

        $this->info("Found {$count} media file(s) on disk \"local\".");

        foreach ($query->cursor() as $media) {
            $this->migrateOne($media, $local, $public, $dryRun);
        }

        if ($dryRun) {
            $this->comment('Dry run only — no files copied or database updated.');
        } else {
            $this->info('Done.');
        }

        return self::SUCCESS;
    }

    private function migrateOne(Media $media, $local, $public, bool $dryRun): void
    {
        $mainRelative = str_replace('\\', '/', $media->getPathRelativeToRoot());
        $directory = dirname($mainRelative);

        if ($directory === '.' || $directory === '') {
            $files = [$mainRelative];
        } else {
            $files = $local->allFiles($directory);
            if ($files === []) {
                $files = $local->exists($mainRelative) ? [$mainRelative] : [];
            }
        }

        if ($files === []) {
            $this->warn("No files on local disk for media id {$media->id} (expected near [{$mainRelative}]).");

            return;
        }

        foreach ($files as $relative) {
            $relative = str_replace('\\', '/', $relative);

            if ($dryRun) {
                $this->line("Would copy local:{$relative} → public:{$relative}");

                continue;
            }

            $public->makeDirectory(dirname($relative));

            $public->put($relative, $local->get($relative));
            $local->delete($relative);
        }

        if ($dryRun) {
            $this->line("Would set media id {$media->id} disk to public.");

            return;
        }

        $media->disk = 'public';
        if ($media->conversions_disk === 'local') {
            $media->conversions_disk = 'public';
        }
        $media->save();

        $this->info("Migrated media id {$media->id} (".count($files).' file(s)).');
    }
}
