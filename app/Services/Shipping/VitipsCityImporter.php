<?php

namespace App\Services\Shipping;

use App\Models\ShippingCompany;
use App\Models\ShippingCompanyCity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VitipsCityImporter
{
    public function resolveVitipsCompany(): ShippingCompany
    {
        $company = ShippingCompany::query()
            ->get()
            ->first(fn (ShippingCompany $c): bool => $this->isVitipsCompany($c->name));

        if ($company === null) {
            throw new RuntimeException(
                'No shipping company found whose name matches Vitips (e.g. contains "Vitips"). Add one in Filament first.'
            );
        }

        return $company;
    }

    private function isVitipsCompany(string $name): bool
    {
        return str_contains(mb_strtolower($name), 'vitips');
    }

    /**
     * Prefers Vitips &lt;option&gt; markup; falls back to Express-style &lt;a class="city-option"&gt; if no options found.
     *
     * @return list<array{name: string, vitips_label: string, aliases: array<int, string>, sort_order: int}>
     */
    public function parseCityAnchorsFromHtml(string $html): array
    {
        $fromOptions = $this->parseFromSelectOptions($html);
        if ($fromOptions !== []) {
            return $fromOptions;
        }

        return $this->parseFromCityOptionAnchors($html);
    }

    /**
     * Vitips &lt;option value="80"&gt;CASABLANCA&lt;/option&gt; submits city=80 — store id separately from the label.
     *
     * @return list<array{name: string, vitips_label: string, vitips_city_id: ?string, aliases: array<int, string>, sort_order: int}>
     */
    private function parseFromSelectOptions(string $html): array
    {
        $rows = [];
        $sort = 0;

        foreach (CityOptionHtmlParser::parseSelectOptions($html) as $opt) {
            $value = $opt['value'];
            $text = trim(preg_replace('/\s+/u', ' ', $opt['text']));

            $isNumericId = $value !== '' && preg_match('/^\d+$/', $value);
            $vitipsCityId = $isNumericId ? $value : null;
            $vitipsLabel = $text !== '' ? $text : $value;

            if ($vitipsLabel === '') {
                continue;
            }

            $name = $text !== '' ? $text : $vitipsLabel;

            $aliases = [];
            foreach ([$value, $text] as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                $lc = mb_strtolower($candidate);
                if ($lc === mb_strtolower($name)) {
                    continue;
                }
                if ($lc === mb_strtolower($vitipsLabel)) {
                    continue;
                }
                if (! in_array($candidate, $aliases, true)) {
                    $aliases[] = $candidate;
                }
            }

            $rows[] = [
                'name' => $name,
                'vitips_label' => $vitipsLabel,
                'vitips_city_id' => $vitipsCityId,
                'aliases' => $aliases,
                'sort_order' => $sort++,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, vitips_label: string, vitips_city_id: ?string, aliases: array<int, string>, sort_order: int}>
     */
    private function parseFromCityOptionAnchors(string $html): array
    {
        $rows = [];
        $sort = 0;

        foreach (CityOptionHtmlParser::parseAnchors($html) as $a) {
            $dataValue = $a['data_value'];
            $dataName = $a['data_name'];
            $label = $a['label'];

            $vitipsLabel = $dataName !== '' ? $dataName : $label;
            if ($vitipsLabel === '') {
                if ($dataValue !== '' && ! preg_match('/^\d+$/', $dataValue)) {
                    $vitipsLabel = $dataValue;
                } else {
                    continue;
                }
            }

            $name = $label !== '' ? $label : ($dataName !== '' ? $dataName : $vitipsLabel);

            $aliases = [];
            foreach ([$dataName, $label, $dataValue] as $candidate) {
                if (! is_string($candidate) || $candidate === '' || preg_match('/^\d+$/', $candidate)) {
                    continue;
                }
                $lc = mb_strtolower($candidate);
                if ($lc === mb_strtolower($name)) {
                    continue;
                }
                if ($lc === mb_strtolower($vitipsLabel)) {
                    continue;
                }
                if (! in_array($candidate, $aliases, true)) {
                    $aliases[] = $candidate;
                }
            }

            $rows[] = [
                'name' => $name,
                'vitips_label' => $vitipsLabel,
                'vitips_city_id' => null,
                'aliases' => $aliases,
                'sort_order' => $sort++,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{name: string, vitips_label: string, vitips_city_id: ?string, aliases: array<int, string>, sort_order: int}>  $rows
     */
    public function importRows(ShippingCompany $company, array $rows): int
    {
        $count = 0;

        DB::transaction(function () use ($company, $rows, &$count): void {
            foreach ($rows as $row) {
                $attrs = [
                    'name' => $row['name'],
                    'vitips_label' => $row['vitips_label'],
                    'vitips_city_id' => $row['vitips_city_id'] ?? null,
                    'aliases' => $row['aliases'] === [] ? null : $row['aliases'],
                    'express_city_code' => null,
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ];

                if (filled($row['vitips_city_id'] ?? null)) {
                    ShippingCompanyCity::query()->updateOrCreate(
                        [
                            'shipping_company_id' => $company->id,
                            'vitips_city_id' => $row['vitips_city_id'],
                        ],
                        $attrs
                    );
                } else {
                    ShippingCompanyCity::query()->updateOrCreate(
                        [
                            'shipping_company_id' => $company->id,
                            'vitips_label' => $row['vitips_label'],
                        ],
                        $attrs
                    );
                }
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
