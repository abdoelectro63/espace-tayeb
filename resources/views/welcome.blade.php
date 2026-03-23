<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Espace Tayeb | فضاء الطيب</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script> </head>
<body class="bg-gray-50 font-sans antialiased">

    <nav class="bg-white shadow-sm p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-600">Espace Tayeb</h1>
            <ul class="flex gap-6 text-gray-600">
                <li><a href="#" class="hover:text-blue-500">الرئيسية</a></li>
                <li><a href="#" class="hover:text-blue-500">المنتجات</a></li>
                <li><a href="#" class="hover:text-blue-500">اتصل بنا</a></li>
            </ul>
        </div>
    </nav>

    <header class="py-16 bg-blue-600 text-white text-center">
        <h2 class="text-4xl font-extrabold mb-4">أفضل الأجهزة المنزلية في عين الشق</h2>
        <p class="text-xl mb-8">جودة عالية، أداء ممتاز، وأسعار تنافسية لبيتك العصري</p>
        <button class="bg-white text-blue-600 px-8 py-3 rounded-full font-bold hover:bg-gray-100 transition">
            تصفح المنتجات
        </button>
    </header>
    <div>

    <section class="container mx-auto py-12 px-4">
        <h3 class="text-2xl font-bold mb-8 text-right">أقسام متجرنا</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div class="p-6 bg-white rounded-lg shadow-md border-t-4 border-blue-500">
                <h4 class="font-bold text-lg">آلات العجن</h4>
            </div>
            <div class="p-6 bg-white rounded-lg shadow-md border-t-4 border-orange-500">
                <h4 class="font-bold text-lg">المقالي الهوائية</h4>
            </div>
            <div class="p-6 bg-white rounded-lg shadow-md border-t-4 border-green-500">
                <h4 class="font-bold text-lg">مطاحن التوابل</h4>
            </div>
        </div>
    </section>

</body>
</html>