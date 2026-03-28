<?php

namespace App\Console\Commands;

use App\Models\ShippingCompanyCity;
use App\Services\Shipping\ExpressCoursierCityImporter;
use Illuminate\Console\Command;

class ImportExpressCoursierCities extends Command
{
    protected $signature = 'express:import-cities
                            {--file= : Single HTML file path (default: database/data/express_coursier_cities.html)}
                            {--fragments : Merge database/data/express_coursier_fragments/part*.html in order}
                            {--fresh : Delete all cities for the Express company before importing (use when replacing the full list)}
                            {--dry-run : Parse only; print how many cities were found (no database writes)}';

    protected $description = 'Import Express Coursier city dropdown HTML into shipping_company_cities for the Express company';

    public function handle(ExpressCoursierCityImporter $importer): int
    {
        $base = database_path('data');

        if ($this->option('fragments')) {
            $dir = $base.DIRECTORY_SEPARATOR.'express_coursier_fragments';
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
            $file = $this->option('file') ?: $base.DIRECTORY_SEPARATOR.'express_coursier_cities.html';

            if (! is_readable($file)) {
                $this->error("HTML file not readable: {$file}");
                $this->line('Save the Express dropdown HTML there, or use --fragments with database/data/express_coursier_fragments/part01.html …');

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
            $this->error('No city-option anchors found. Check HTML structure (class city-option, data-value, data-name).');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: parsed '.count($rows).' city row(s) from the HTML.');
            $this->line('If this number is too low, paste the full Express dropdown markup into database/data/express_coursier_cities.html (or split into express_coursier_fragments/part*.html).');

            return self::SUCCESS;
        }

        try {
            $company = $importer->resolveExpressCompany();
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
