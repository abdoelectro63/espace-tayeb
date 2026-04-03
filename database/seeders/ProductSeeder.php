<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks to allow truncating and re-inserting
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('products')->truncate();

        $products = [
            [
                'id' => 10,
                'price' => 150,
                'discount_price' => 60,
                'images' => '["products/gallery/c6f773a3-101f-4956-8cac-aab935b59c25.webp","products/gallery/a70e6f3f-9a90-4cd2-9146-4fee6e339b75.webp"]',
                'is_active' => 1,
                'created_at' => '2026-04-01 14:42:38',
                'updated_at' => '2026-04-01 14:42:38',
                'category_id' => 3,
                'main_image' => 'products/titles/1aec039e-528a-4d15-98fd-9586785e291c.webp',
                'stock' => 20,
                'free_shipping' => 0,
                'track_stock' => 1,
                'code' => 'has84367',
                'upsell_id' => null,
                'offer_type' => 'none',
                'offer_value' => null,
                'name' => 'هاشوار يدوية احترافية 2 لتر - هيكل إينوكس صحي و 4 شفرات حادة',
                'description' => 'هني راسك من تمارة ديال التقطيع مع هاد الهاشوار اليدوية العجيبة!...',
                'slug' => 'hachoir-manual',
            ],
            [
                'id' => 11,
                'price' => 700,
                'discount_price' => 500,
                'images' => '["products/gallery/33c9015e-55ad-4405-aa9a-1a0e5c36e7bb.webp"]',
                'is_active' => 1,
                'created_at' => '2026-04-01 14:42:39',
                'updated_at' => '2026-04-01 14:43:08',
                'category_id' => 3,
                'main_image' => 'products/titles/27fc3ca4-15e1-4fb2-91bd-a75a272de532.webp',
                'stock' => 10,
                'free_shipping' => 0,
                'track_stock' => 1,
                'code' => 'tkm15443',
                'upsell_id' => null,
                'offer_type' => 'none',
                'offer_value' => null,
                'name' => 'طقم كاسرول (إينوكس) – 5 قطع من كرافت لاين',
                'description' => 'اكتشف طقم القدور المصنوع من الستانلس ستيل عالي الجودة...',
                'slug' => 'casserole-inox-5pcs',
            ],
            [
                'id' => 12,
                'price' => 160,
                'discount_price' => 100,
                'images' => '["products/gallery/ab3da38a-13ca-48bc-8d7a-010bb9db0db3.webp","products/gallery/8012e4f3-fb39-41d1-b67b-db9a77345f16.webp"]',
                'is_active' => 1,
                'created_at' => '2026-04-01 14:42:39',
                'updated_at' => '2026-04-01 14:43:17',
                'category_id' => 3,
                'main_image' => 'products/titles/28d09aeb-b220-40a1-a553-233b8ae77034.webp',
                'stock' => 10,
                'free_shipping' => 0,
                'track_stock' => 1,
                'code' => '6kt37392',
                'upsell_id' => null,
                'offer_type' => 'none',
                'offer_value' => null,
                'name' => '6 قطع من مول كيك بقياسات مختلفة',
                'description' => null,
                'slug' => '6-moule-cateau',
            ],
            [
                'id' => 13,
                'price' => 100,
                'discount_price' => 80,
                'images' => '["products/gallery/ca14e7cf-5f18-44a9-b54e-d129a727393b.webp"]',
                'is_active' => 1,
                'created_at' => '2026-04-01 14:42:40',
                'updated_at' => '2026-04-01 14:43:27',
                'category_id' => 3,
                'main_image' => 'products/titles/3abe39b1-2d91-477b-8bcd-331f095479a3.webp',
                'stock' => 10,
                'free_shipping' => 0,
                'track_stock' => 1,
                'code' => 'abr23332',
                'upsell_id' => null,
                'offer_type' => 'none',
                'offer_value' => null,
                'name' => 'إبريق شاي انوكس فاخر',
                'description' => 'إبريق شاي أنيق بتصميم كروم لامع مستوحى من الطراز المغربي التقليدي...',
                'slug' => 'theiere-chromee-48oz',
            ],
            [
                'id' => 14, 'price' => 280, 'discount_price' => 200, 'images' => null, 'is_active' => 1,
                'created_at' => '2026-04-01 15:45:05', 'updated_at' => '2026-04-01 15:45:05', 'category_id' => 3,
                'main_image' => 'products/titles/6b98107f-3939-4771-8149-17a8f7bec547.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mkl27248', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'مقلاة قلي انوكس 26 cm – جودة عالية',
                'description' => 'poele-inox-frire-26', 'slug' => 'poele-inox-frire-26'
            ],
            [
                'id' => 15, 'price' => 250, 'discount_price' => 180, 'images' => '["products/gallery/8ba9dcde-bf6b-4fe4-993a-d7913d49a392.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 15:45:05', 'updated_at' => '2026-04-01 15:45:05', 'category_id' => 3,
                'main_image' => 'products/titles/fc18a51f-d49b-4cc6-82d2-547f16bcc7d1.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mkl20510', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'مقلاة انوكس 28 cm – جودة عالية',
                'description' => 'مقلاة انوكس 28 cm – جودة عالية', 'slug' => 'poele-inox-normal-26'
            ],
            [
                'id' => 16, 'price' => 120, 'discount_price' => 80, 'images' => null, 'is_active' => 1,
                'created_at' => '2026-04-01 15:45:05', 'updated_at' => '2026-04-01 15:45:05', 'category_id' => 3,
                'main_image' => 'products/titles/a7e3d12a-8050-4c95-97d7-7975d8212acd.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mol60706', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'مول غرانيت عادي الجودة',
                'description' => 'مول غرانيت عادي الجودة', 'slug' => 'moule-granit'
            ],
            [
                'id' => 17, 'price' => 220, 'discount_price' => 150, 'images' => '["products/gallery/70d47f73-cbe4-4962-9717-e3db2f057a0a.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 15:45:06', 'updated_at' => '2026-04-01 15:45:06', 'category_id' => 3,
                'main_image' => 'products/titles/b96c4673-4ec0-4123-87ca-9e8ed93628b7.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'ghl34356', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'غلاي ماء انوكس عالي الجودة (مقراج)',
                'description' => 'غلاي ماء انوكس عالي الجودة (مقراج)', 'slug' => 'bouilloire-inox-normal'
            ],
            [
                'id' => 18, 'price' => 130, 'discount_price' => 70, 'images' => '["products/gallery/f7ac9bd7-5569-4abb-908e-fe95c042f737.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 15:45:06', 'updated_at' => '2026-04-01 15:45:06', 'category_id' => 3,
                'main_image' => 'products/titles/30f26e0d-5a7f-491d-822f-97184b235936.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'loh03070', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'لوح تقطيع خشبي مع عود بومبو',
                'description' => 'لوح تقطيع خشبي مع عود بومبو', 'slug' => 'planche-bois-bombom'
            ],
            [
                'id' => 19, 'price' => 750, 'discount_price' => 550, 'images' => '["products/gallery/26d748e7-cf95-4fe8-8fb1-8cedb08403ca.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 15:45:06', 'updated_at' => '2026-04-01 15:45:06', 'category_id' => 3,
                'main_image' => 'products/titles/e98a32ab-0b81-43e6-93cf-87ddbaf49483.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'tkm99009', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'طقم يتكون من 36 طبسيل - 36pcs set dinner',
                'description' => 'طقم يتكون من 36 طبسيل - 36pcs set dinner', 'slug' => '36-pcs-set-dinner'
            ],
            [
                'id' => 20, 'price' => 160, 'discount_price' => 120, 'images' => '["products/gallery/490acf86-fb9e-454d-bd8c-151dee7e9561.webp","products/gallery/c5972eb4-fb3f-4837-b984-c3f181ec299c.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 16:25:21', 'updated_at' => '2026-04-01 16:30:36', 'category_id' => 4,
                'main_image' => 'products/titles/949ca404-ab59-4d4c-9208-0e36409789aa.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'ric14247', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Richou inox electrique - موقد نار كهربائي انوكس',
                'description' => 'Richou inox - موقد نار كهربائي انوكس', 'slug' => 'richou-electrique-inox'
            ],
            [
                'id' => 21, 'price' => 150, 'discount_price' => 100, 'images' => '["products/gallery/a36df06f-e97c-4742-8430-261cec1f7c4b.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 16:25:21', 'updated_at' => '2026-04-01 16:30:36', 'category_id' => 4,
                'main_image' => 'products/titles/de3bc772-7961-4d8b-9cfa-6528c8eb3e6c.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'ric94030', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Richou electrique - موقد نار كهربائي',
                'description' => 'Richou inox - موقد نار كهربائي انوكس', 'slug' => 'richou-electrique'
            ],
            [
                'id' => 22, 'price' => 280, 'discount_price' => 220, 'images' => '["products/gallery/15a6d083-528b-4372-8145-7bcc8b65957b.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 16:25:22', 'updated_at' => '2026-04-01 16:30:36', 'category_id' => 4,
                'main_image' => 'products/titles/066d5106-6047-4ed3-b99f-717f3170f7a4.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mko07921', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'مكواة بخار يدوية - Garment Steamer',
                'description' => 'مكواة بخار يدوية - Garment Steamer', 'slug' => 'garment-steamer'
            ],
            [
                'id' => 23, 'price' => 600, 'discount_price' => 450, 'images' => '["products/gallery/1cfd0f33-f629-4fbe-8a34-d4cf0df8051a.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 16:25:22', 'updated_at' => '2026-04-01 16:30:36', 'category_id' => 4,
                'main_image' => 'products/titles/bf13f391-07ed-467c-9b9c-c42a03c9676f.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'pla54936', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Plancha grill 90cm - مقلة طويلة 90 سنتمتر',
                'description' => 'Plancha grill 90cm', 'slug' => 'planche-grill-90cm'
            ],
            [
                'id' => 24, 'price' => 280, 'discount_price' => 200, 'images' => '["products/gallery/1358463a-b73d-4207-8393-c512aaecc6ed.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 16:25:22', 'updated_at' => '2026-04-01 16:30:37', 'category_id' => 4,
                'main_image' => 'products/titles/cb40e183-33c5-4880-9693-90ca43f09c61.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'pan29979', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Panini granit ress - الة بانيني غرانيت',
                'description' => 'Panini granit ress - الة بانيني غرانيت', 'slug' => 'panini-granit-ress'
            ],
            [
                'id' => 25, 'price' => 280, 'discount_price' => 220, 'images' => '["products/gallery/dd23ac4d-c512-4604-a3ba-1b4e4ef78fd1.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 16:25:22', 'updated_at' => '2026-04-01 16:30:37', 'category_id' => 4,
                'main_image' => 'products/titles/ebdbf945-50da-4aa5-9970-df69a7e404e8.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mix98517', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Mixeur 3 en - 1 خلاط كهربائي 3 في 1',
                'description' => 'Mixeur 3 en - 1 خلاط كهربائي 3 في 1', 'slug' => 'mixeur-3-en-1'
            ],
            [
                'id' => 26, 'price' => 180, 'discount_price' => 120, 'images' => null, 'is_active' => 1,
                'created_at' => '2026-04-01 16:25:22', 'updated_at' => '2026-04-01 16:30:37', 'category_id' => 4,
                'main_image' => 'products/titles/79143dc1-15d2-4feb-a8c7-0b2cd4178579.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'kra13643', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Kraft line Mixeur plongeant 2 en 1 - خلاط كهربائي يدوي',
                'description' => 'Kraft line Mixeur plongeant 2 en 1 - خلاط كهربائي يدوي', 'slug' => 'mixeur-normal'
            ],
            [
                'id' => 27, 'price' => 450, 'discount_price' => 350, 'images' => '["products/gallery/6db17cbd-c582-45be-ba2b-61630e968cfc.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 16:25:22', 'updated_at' => '2026-04-01 16:30:37', 'category_id' => 4,
                'main_image' => 'products/titles/41c2fd31-7ace-4e34-bead-afde311ba364.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'gri20539', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Grinder 150g - طحانة العطرية 150 غرام',
                'description' => 'Grinder 150g - طحانة العطرية 150 غرام', 'slug' => 'grinder-150g'
            ],
            [
                'id' => 28, 'price' => 90, 'discount_price' => 50, 'images' => '["products/gallery/509f35d5-fb8e-4989-bef4-e3fee6618a66.webp","products/gallery/9bf7f949-1ee3-4da2-8329-3c577b20868a.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 17:01:20', 'updated_at' => '2026-04-01 17:01:20', 'category_id' => 3,
                'main_image' => 'products/titles/4a536a8d-c1cb-4d1c-b1ba-442d8fb5a266.webp', 'stock' => 30,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mnt76533', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'منظمة العطرية صغيرة عالية الجودة - Ensemble de Condiments 4 Pièces avec Support',
                'description' => 'Ensemble de Condiments 4 Pièces avec Support', 'slug' => 'ensemble-de-condiments-4pcs'
            ],
            [
                'id' => 29, 'price' => 180, 'discount_price' => 130, 'images' => '["products/gallery/3de45715-f59a-401b-ae45-4884eafea9ae.webp","products/gallery/1172a777-fc5c-4241-9f73-84a4527f14bc.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 17:01:20', 'updated_at' => '2026-04-01 17:01:20', 'category_id' => 3,
                'main_image' => 'products/titles/74835427-0821-49c5-b431-104495fe8733.webp', 'stock' => 30,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => '18j83992', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => '18 Jar Rotating Spice Rack | رف توابل دوار 18 قارورة',
                'description' => '18 Jar Rotating Spice Rack | رف توابل دوار 18 قارورة', 'slug' => 'rotating-spice-rack-18'
            ],
            [
                'id' => 30, 'price' => 400, 'discount_price' => 300, 'images' => '["products/gallery/bead4b4d-cc57-42ae-a028-4aa19ea1f64c.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 17:01:20', 'updated_at' => '2026-04-01 17:01:20', 'category_id' => 3,
                'main_image' => 'products/titles/768a4040-fc6b-420c-bb5b-44d80ddcbead.webp', 'stock' => 30,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mnt81390', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'منظمة الخضروات والفواكه 5 طبقات جودة 1 | Chariot de rangement rotatif',
                'description' => 'Chariot de rangement rotatif', 'slug' => 'chariot-de-rangement-5'
            ],
            [
                'id' => 31, 'price' => 120, 'discount_price' => 80, 'images' => '["products/gallery/9ec44cfc-7417-400c-a4bc-1d38b88f5c51.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 17:23:10', 'updated_at' => '2026-04-01 17:23:10', 'category_id' => 4,
                'main_image' => 'products/titles/718653e8-744e-43b8-a01d-34b4cbbec484.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'min39205', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Mini Richou electrique - موقد نار كهربائي صغير',
                'description' => 'Mini Richou electrique - موقد نار كهربائي صغير', 'slug' => 'mini-richou-electrique'
            ],
            [
                'id' => 32, 'price' => 100, 'discount_price' => 60, 'images' => '["products/gallery/ad7f40d9-f57a-418e-8b17-6cc4ba5481d7.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 17:23:10', 'updated_at' => '2026-04-01 17:23:10', 'category_id' => 4,
                'main_image' => 'products/titles/885b64ce-dd0b-49ad-aadc-11738c06e8fe.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mdk16521', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'مضخة مياه كهربائية | Generic Pompe à eau électrique',
                'description' => 'Generic Pompe à eau électrique', 'slug' => 'generic-pompe-electrique'
            ],
            [
                'id' => 33, 'price' => 250, 'discount_price' => 150, 'images' => '["products/gallery/7f9a3622-c10b-471b-a590-c1c364af8362.webp","products/gallery/fea789cd-df0f-463b-a591-865748809112.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 17:23:11', 'updated_at' => '2026-04-01 17:23:11', 'category_id' => 5,
                'main_image' => 'products/titles/e167b9a5-c72f-477f-a242-ccff2ce53728.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'tau06880', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Taurus Tondeuse | ماكينة حلاقة الشعر',
                'description' => 'Taurus Tondeuse | ماكينة حلاقة الشعر', 'slug' => 'taurus-tendeuse'
            ],
            [
                'id' => 34, 'price' => 350, 'discount_price' => 200, 'images' => '["products/gallery/4223cf36-0871-4107-ad6d-d4c4d713cc4d.webp","products/gallery/891329b7-9d94-41a9-966a-abb29e7cfafd.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 17:23:11', 'updated_at' => '2026-04-01 17:23:11', 'category_id' => 6,
                'main_image' => 'products/titles/b7198047-9d95-4adf-81b4-b07b01ded5c5.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mdh89560', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'MDHL car washer | مضغة غسل الماء للسيارات و دراجات',
                'description' => 'MDHL car washer', 'slug' => 'mdhl-car-washer'
            ],
            [
                'id' => 35, 'price' => 200, 'discount_price' => 100, 'images' => '["products/gallery/406d8bb7-057b-4a44-8988-e4e657439339.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 19:30:33', 'updated_at' => '2026-04-01 19:30:33', 'category_id' => 4,
                'main_image' => 'products/titles/310015c1-03b6-436f-80e8-a52d31133d14.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'ble18711', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Blender electrique - خلاط كهربائي',
                'description' => 'Blender electrique - خلاط كهربائي', 'slug' => 'blender-electrique'
            ],
            [
                'id' => 36, 'price' => 650, 'discount_price' => 560, 'images' => '["products/gallery/3e8240ab-eada-4055-abb5-8bab1397076b.webp","products/gallery/32201413-1eab-4021-a384-3a6134ed736e.webp","products/gallery/c000d5ea-b821-4695-985e-9c9c4fe38b51.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 19:30:34', 'updated_at' => '2026-04-01 19:30:34', 'category_id' => 4,
                'main_image' => 'products/titles/3c4f66e6-fce7-4953-906f-aed8e2791038.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'asp89674', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'aspirateur eau et poussière - مكنسة كهربائية للماء و الخبار',
                'description' => 'aspirateur eau et poussière', 'slug' => 'aspirateur-eau-poussiere'
            ],
            [
                'id' => 37, 'price' => 900, 'discount_price' => 750, 'images' => '["products/gallery/67d5014e-74cb-43c1-aa94-94eaa51317dc.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 19:30:34', 'updated_at' => '2026-04-01 19:30:34', 'category_id' => 4,
                'main_image' => 'products/titles/cfc05aa4-4aa1-4c5e-940a-ca4410ad2df3.webp', 'stock' => 20,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'mac53700', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Machine cafe venezia 3 en 1 - الة القهوة فينيزيا',
                'description' => 'Machine cafe venezia 3 en 1', 'slug' => 'machine-cafe-venezia-3-en-1'
            ],
            [
                'id' => 38, 'price' => 350, 'discount_price' => 260, 'images' => '["products/gallery/fd5a8c92-2ed4-4e8d-b938-8f4e4905d314.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 19:50:36', 'updated_at' => '2026-04-01 19:52:30', 'category_id' => 4,
                'main_image' => 'products/titles/c2506ef3-45d5-426f-afb4-c4b825d41145.webp', 'stock' => 0,
                'free_shipping' => 0, 'track_stock' => 0, 'code' => 'fou64135', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Four venezia 9l - فرن كهربائي فينيزيا',
                'description' => 'فرن صغير سعة 9 لترات مؤقت 60 دقيقة...', 'slug' => 'four-venezia-9l'
            ],
            [
                'id' => 39, 'price' => 1500, 'discount_price' => 1300, 'images' => '["products/gallery/b2c59081-d748-4235-afc3-436ac93968c9.webp","products/gallery/60549a46-e9d9-4cfb-9bf4-02c345c3f99a.webp","products/gallery/88ac590b-df45-4844-b3ed-727115484cb5.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 19:57:06', 'updated_at' => '2026-04-01 19:57:06', 'category_id' => 4,
                'main_image' => 'products/titles/8e6fc80e-670f-4085-95e0-eb9b865b6a1e.webp', 'stock' => 0,
                'free_shipping' => 0, 'track_stock' => 0, 'code' => 'fou17525', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Four itimat 60l - فرن ايتيمات',
                'description' => 'Double Glass Turbo fan lampe interne Made in Turkey', 'slug' => 'four-itimat-60l'
            ],
            [
                'id' => 40, 'price' => 1400, 'discount_price' => 1200, 'images' => '["products/gallery/5f2cf433-f5f9-4d45-87bf-970c476f8bb4.webp","products/gallery/7eb4ff55-675c-4b94-bc43-fe01f8c53c67.webp","products/gallery/9bd9c92e-da72-4b6a-af30-b4dbcd16d349.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 19:58:39', 'updated_at' => '2026-04-01 19:58:39', 'category_id' => 4,
                'main_image' => 'products/titles/f24409fb-2707-4cfa-b43b-b09299aa56f3.webp', 'stock' => 0,
                'free_shipping' => 0, 'track_stock' => 0, 'code' => 'fou59226', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Four itimat 50l - فرن ايتيمات',
                'description' => 'Four Électrique itimat 50L', 'slug' => 'four-itimat-50l'
            ],
            [
                'id' => 41, 'price' => 200, 'discount_price' => 120, 'images' => '["products/gallery/f43c5553-65ee-45a7-b262-175fb7bedb23.webp","products/gallery/28b8fdc6-77bd-4c5e-83b9-b35d3075a615.webp","products/gallery/b421f4f6-fc75-4186-b2f0-082d6ebdb11a.webp"]', 'is_active' => 1,
                'created_at' => '2026-04-01 20:02:57', 'updated_at' => '2026-04-01 20:02:57', 'category_id' => 4,
                'main_image' => 'products/titles/e15d1fb8-5063-46bd-ba03-8f3603feae70.webp', 'stock' => 0,
                'free_shipping' => 0, 'track_stock' => 0, 'code' => 'toa07487', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Toast maker - الة تحميص توست',
                'description' => 'يُعد صانع الساندويتشات جهازًا عمليًا وسريعًا...', 'slug' => 'toast-maker-4'
            ],
            [
                'id' => 42, 'price' => 500, 'discount_price' => 350, 'images' => '["products/gallery/915c7a46-4b58-41..."]', 'is_active' => 1,
                'created_at' => '2026-04-01 21:00:00', 'updated_at' => '2026-04-01 21:00:00', 'category_id' => 4,
                'main_image' => 'products/titles/example_image.webp', 'stock' => 10,
                'free_shipping' => 0, 'track_stock' => 1, 'code' => 'ext12345', 'upsell_id' => null,
                'offer_type' => 'none', 'offer_value' => null, 'name' => 'Product 42 Name',
                'description' => 'Description for product 42', 'slug' => 'slug-42'
            ]
        ];

        foreach ($products as $product) {
            DB::table('products')->updateOrInsert(['id' => $product['id']], $product);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}