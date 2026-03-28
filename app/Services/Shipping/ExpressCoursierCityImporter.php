<?php

namespace App\Services\Shipping;

use App\Models\ShippingCompany;
use App\Models\ShippingCompanyCity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpressCoursierCityImporter
{
    public function resolveExpressCompany(): ShippingCompany
    {
        $company = ShippingCompany::query()
            ->get()
            ->first(fn (ShippingCompany $c): bool => $this->isExpressCoursierCompany($c->name));

        if ($company === null) {
            throw new RuntimeException(
                'No shipping company found whose name matches Express Coursier (e.g. contains "Express Coursier"). Add one in Filament first.'
            );
        }

        return $company;
    }

    private function isExpressCoursierCompany(string $name): bool
    {
        $normalized = str_replace([' ', '-', '_'], '', mb_strtolower($name));

        return str_contains($normalized, 'expresscoursier');
    }

    /**
     * @return list<array{name: string, express_city_code: string, aliases: array<int, string>, sort_order: int}>
     */
    public function parseCityAnchorsFromHtml(string $html): array
    {
        $rows = [];
        $sort = 0;

        foreach (CityOptionHtmlParser::parseAnchors($html) as $a) {
            $code = $a['data_value'];
            if ($code === '' || ! preg_match('/^\d+$/', $code)) {
                continue;
            }

            $dataName = $a['data_name'];
            $label = $a['label'];

            $name = $label !== '' ? $label : $dataName;
            if ($name === '') {
                continue;
            }

            $aliases = [];
            if ($dataName !== '' && mb_strtolower($dataName) !== mb_strtolower($name)) {
                $aliases[] = $dataName;
            }

            $rows[] = [
                'name' => $name,
                'express_city_code' => $code,
                'aliases' => $aliases,
                'sort_order' => $sort++,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{name: string, express_city_code: string, aliases: array<int, string>, sort_order: int}>  $rows
     */
    public function importRows(ShippingCompany $company, array $rows): int
    {
        $count = 0;

        DB::transaction(function () use ($company, $rows, &$count): void {
            foreach ($rows as $row) {
                ShippingCompanyCity::query()->updateOrCreate(
                    [
                        'shipping_company_id' => $company->id,
                        'express_city_code' => $row['express_city_code'],
                    ],
                    [
                        'name' => $row['name'],
                        'aliases' => $row['aliases'] === [] ? null : $row['aliases'],
                        'vitips_label' => null,
                        'sort_order' => $row['sort_order'],
                        'is_active' => true,
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  list<string>  $absolutePaths
     */
    public function mergeHtmlFragments(array $absolutePaths): string
    {
        $html = '';

        foreach ($absolutePaths as $path) {
            if (is_readable($path)) {
                $html .= file_get_contents($path);
            }
        }

        return $html;
    }
}
