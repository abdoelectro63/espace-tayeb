<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create the Menus (Locations)
        $footer1 = Menu::create([
            'id' => 1,
            'name' => 'التصنيفات',
            'location' => 'footer_1',
            'created_at' => '2026-04-01 17:53:51',
            'updated_at' => '2026-04-01 17:53:51',
        ]);

        $topMenu = Menu::create([
            'id' => 2,
            'name' => 'top menu',
            'location' => 'top_menu',
            'created_at' => '2026-04-01 17:58:17',
            'updated_at' => '2026-04-01 17:58:17',
        ]);

        $footer3 = Menu::create([
            'id' => 3,
            'name' => 'السياسات',
            'location' => 'footer_3',
            'created_at' => '2026-04-01 18:22:50',
            'updated_at' => '2026-04-02 15:21:08',
        ]);

        $footer2 = Menu::create([
            'id' => 4,
            'name' => 'التصنيفات 2',
            'location' => 'footer_2',
            'created_at' => '2026-04-02 13:33:23',
            'updated_at' => '2026-04-02 13:34:03',
        ]);

        // 2. Create Menu Items for Footer 1 (Categories)
        $footer1Items = [
            ['label' => 'ادوات المطبخ', 'order' => 1, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 3],
            ['label' => 'الاجهزة المنزلية', 'order' => 2, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 4],
            ['label' => 'بريكولاج', 'order' => 3, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 6],
            ['label' => 'صحة و جمال', 'order' => 4, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 5],
        ];

        foreach ($footer1Items as $item) {
            MenuItem::create(array_merge($item, ['menu_id' => $footer1->id]));
        }

        // 3. Create Menu Items for Top Menu
        $topMenuItems = [
            ['label' => 'اواني المطبخ', 'order' => 1, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 3],
            ['label' => 'الاجهزة المنزلية', 'order' => 2, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 4],
            ['label' => 'بريكولاج', 'order' => 3, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 6],
            ['label' => 'صحة و جمال', 'order' => 4, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 5],
        ];

        foreach ($topMenuItems as $item) {
            MenuItem::create(array_merge($item, ['menu_id' => $topMenu->id]));
        }

        // 4. Create Menu Items for Footer 3 (Policies/Pages)
        $footer3Items = [
            ['label' => 'سياسة الإرجاع والاسترداد والتبادل', 'order' => 1, 'linkable_type' => 'App\Models\Page', 'linkable_id' => 2],
            ['label' => 'سياسة الخصوصية وحماية البيانات الشخصية', 'order' => 2, 'linkable_type' => 'App\Models\Page', 'linkable_id' => 1],
        ];

        foreach ($footer3Items as $item) {
            MenuItem::create(array_merge($item, ['menu_id' => $footer3->id]));
        }

        // 5. Create Menu Items for Footer 2 (Sub-Categories)
        $footer2Items = [
            ['label' => 'أواني الشرب', 'order' => 1, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 7],
            ['label' => 'الة القهوة', 'order' => 2, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 8],
            ['label' => 'بريكولاج - اعمال منزلية', 'order' => 3, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 6],
        ];

        foreach ($footer2Items as $item) {
            MenuItem::create(array_merge($item, ['menu_id' => $footer2->id]));
        }
    }
}