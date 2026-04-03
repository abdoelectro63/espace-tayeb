<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::upsert(
            [
                [
                    'id' => 3,
                    'name' => 'اواني المنزلية',
                    'slug' => 'kitchenware',
                    'category_id' => null,
                    'icon' => 'heroicon-o-adjustments-vertical',
                    'image' => 'categories/images/359b9d84-cd78-45b7-9c0d-8ff91ae391ff.webp',
                    'created_at' => '2026-04-01 13:39:28',
                    'updated_at' => '2026-04-02 15:28:21',
                ],
                [
                    'id' => 4,
                    'name' => 'الاجهزة المنزلية',
                    'slug' => 'electromenager',
                    'category_id' => null,
                    'icon' => null,
                    'image' => 'categories/images/9daa8d3f-35d3-4fd4-ad14-e4c1ad88a16f.webp',
                    'created_at' => '2026-04-01 15:47:48',
                    'updated_at' => '2026-04-01 15:47:48',
                ],
                [
                    'id' => 5,
                    'name' => 'صحة و جمال',
                    'slug' => 'Sante-et-beaute',
                    'category_id' => null,
                    'icon' => null,
                    'image' => 'categories/images/e41ce90c-109a-40e3-a981-2e69a17ccc65.webp',
                    'created_at' => '2026-04-01 17:14:42',
                    'updated_at' => '2026-04-01 17:14:42',
                ],
                [
                    'id' => 6,
                    'name' => 'بريكولاج - اعمال منزلية',
                    'slug' => 'bricolage',
                    'category_id' => null,
                    'icon' => null,
                    'image' => 'categories/images/fc79b152-1619-4447-80a5-1608f139efae.webp',
                    'created_at' => '2026-04-01 17:20:08',
                    'updated_at' => '2026-04-01 17:20:08',
                ],
                [
                    'id' => 7,
                    'name' => 'أواني الشرب',
                    'slug' => 'awani-chourb',
                    'category_id' => 3,
                    'icon' => null,
                    'image' => 'categories/images/aa696edf-0046-4449-a4be-4e445c4d5ec7.webp',
                    'created_at' => '2026-04-02 12:22:59',
                    'updated_at' => '2026-04-02 12:27:38',
                ],
                [
                    'id' => 8,
                    'name' => 'الة القهوة',
                    'slug' => 'machine-cafe',
                    'category_id' => 4,
                    'icon' => null,
                    'image' => 'categories/images/62cb9753-ba99-4039-a116-213ddd2163ba.webp',
                    'created_at' => '2026-04-02 13:14:30',
                    'updated_at' => '2026-04-02 13:26:07',
                ],
            ],
            ['id'],
            ['name', 'slug', 'category_id', 'icon', 'image', 'created_at', 'updated_at']
        );
    }
}
