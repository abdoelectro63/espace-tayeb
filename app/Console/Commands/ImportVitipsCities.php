<?php

namespace App\Console\Commands;

use App\Models\ShippingCompanyCity;
use App\Services\Shipping\VitipsCityImporter;
use Illuminate\Console\Command;

class ImportVitipsCities extends Command
{
    protected $signature = 'vitips:import-cities
                            {--file= : Single HTML file path (default: database/data/vitips_cities.html)}
                            {--fragments : Merge database/data/vitips_cities_fragments/part*.html in order}
                            {--fresh : Delete all cities for the Vitips company before importing}
                            {--dry-run : Parse only; print how many cities were found (no database writes)}';

    protected $description = 'Import Vitips city dropdown HTML into shipping_company_cities for the Vitips company';

    public function handle(VitipsCityImporter $importer): int
    {
        $base = database_path('data');

        if ($this->option('fragments')) {
            $dir = $base.DIRECTORY_SEPARATOR.'vitips_cities_fragments';
            if (! is_dir($dir)) {
                $this->error("Directory not found: {$dir}");

                return self::FAILURE;
            }

            $paths = collect(glob($dir.DIRECTORY_SEPARATOR.'part*.html') ?: [])
                ->sort()
                ->values()
                ->all();

            if ($paths === []) {
                $this->error("No part*.html files in {$dir}");

                return self::FAILURE;
            }

            $html = $importer->mergeHtmlFragments($paths);
        } else {
            $file = $this->option('file') ?: $base.DIRECTORY_SEPARATOR.'vitips_cities.html';

            if (! is_readable($file)) {
                $this->error("HTML file not readable: {$file}");
                $this->line('Save the Vitips dropdown HTML there, or use --fragments with database/data/vitips_cities_fragments/part01.html …');

                return self::FAILURE;
            }

            $html = (string) file_get_contents($file);
        }

        if (trim($html) === '') {
            $this->error('HTML is empty.');

            return self::FAILURE;
        }

        $rows = $importer->parseCityAnchorsFromHtml($html);

        if ($rows === []) {
            $this->error('No cities found. Vitips expects <option value="…">…</option> (or fallback: <a class="city-option"> with data-name).');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: parsed '.count($rows).' city row(s) from the HTML.');
            $this->line('If this number is too low, paste the full Vitips dropdown markup into database/data/vitips_cities.html.');

            return self::SUCCESS;
        }

        try {
            $company = $importer->resolveVitipsCompany();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $deleted = ShippingCompanyCity::query()
                ->where('shipping_company_id', $company->id)
                ->delete();
            $this->comment("Removed {$deleted} existing city row(s) for this company.");
        }

        $n = $importer->importRows($company, $rows);

        $this->info("Imported {$n} cities for [{$company->name}] (id {$company->id}).");

        return self::SUCCESS;
    }
}
