<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeders extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $footer1 = Menu::updateOrCreate(
            ['location' => 'footer_1'],
            ['name' => 'التصنيفات']
        );

        $topMenu = Menu::updateOrCreate(
            ['location' => 'top_menu'],
            ['name' => 'top menu']
        );

        $footer3 = Menu::updateOrCreate(
            ['location' => 'footer_3'],
            ['name' => 'السياسات']
        );

        $footer2 = Menu::updateOrCreate(
            ['location' => 'footer_2'],
            ['name' => 'التصنيفات 2']
        );

        $footer1Items = [
            ['label' => 'ادوات المطبخ', 'order' => 1, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 3],
            ['label' => 'الاجهزة المنزلية', 'order' => 2, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 4],
            ['label' => 'بريكولاج', 'order' => 3, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 6],
            ['label' => 'صحة و جمال', 'order' => 4, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 5],
        ];

        foreach ($footer1Items as $item) {
            MenuItem::updateOrCreate(
                ['menu_id' => $footer1->id, 'order' => $item['order']],
                array_merge($item, ['menu_id' => $footer1->id])
            );
        }

        $topMenuItems = [
            ['label' => 'اواني المطبخ', 'order' => 1, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 3],
            ['label' => 'الاجهزة المنزلية', 'order' => 2, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 4],
            ['label' => 'بريكولاج', 'order' => 3, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 6],
            ['label' => 'صحة و جمال', 'order' => 4, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 5],
        ];

        foreach ($topMenuItems as $item) {
            MenuItem::updateOrCreate(
                ['menu_id' => $topMenu->id, 'order' => $item['order']],
                array_merge($item, ['menu_id' => $topMenu->id])
            );
        }

        $footer3Items = [
            ['label' => 'سياسة الإرجاع والاسترداد والتبادل', 'order' => 1, 'linkable_type' => 'App\Models\Page', 'linkable_id' => 2],
            ['label' => 'سياسة الخصوصية وحماية البيانات الشخصية', 'order' => 2, 'linkable_type' => 'App\Models\Page', 'linkable_id' => 1],
        ];

        foreach ($footer3Items as $item) {
            MenuItem::updateOrCreate(
                ['menu_id' => $footer3->id, 'order' => $item['order']],
                array_merge($item, ['menu_id' => $footer3->id])
            );
        }

        $footer2Items = [
            ['label' => 'أواني الشرب', 'order' => 1, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 7],
            ['label' => 'الة القهوة', 'order' => 2, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 8],
            ['label' => 'بريكولاج - اعمال منزلية', 'order' => 3, 'linkable_type' => 'App\Models\Category', 'linkable_id' => 6],
        ];

        foreach ($footer2Items as $item) {
            MenuItem::updateOrCreate(
                ['menu_id' => $footer2->id, 'order' => $item['order']],
                array_merge($item, ['menu_id' => $footer2->id])
            );
        }
    }
}
