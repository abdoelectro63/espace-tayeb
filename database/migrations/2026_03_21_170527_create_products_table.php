<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم المنتج (مثلاً: مقلاة هوائية 5 لتر)
            $table->text('description')->nullable(); // الوصف
            $table->decimal('price', 10, 2); // الثمن الأصلي
            $table->decimal('discount_price', 10, 2)->nullable(); // الثمن بعد التخفيض
           // $table->json('images')->nullable()->after('description');
           $table->json('images')->nullable();
            $table->boolean('is_active')->default(true); // هل المنتج معروض أم مخفي
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
