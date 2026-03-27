<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExampleExport implements FromArray, WithHeadings
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'name',
            'slug',
            'description',
            'price',
            'old_price',
            'qty',
            'category_id',
            'image_url',
            'additional_images',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [
            [
                'Blender Pro 2L',
                'blender-pro-2l',
                'Powerful kitchen blender with 2L jar.',
                499,
                699,
                10,
                1,
                'https://example.com/images/blender.jpg',
                'https://example.com/images/blender-side.jpg, https://example.com/images/blender-back.jpg',
            ],
        ];
    }
}
