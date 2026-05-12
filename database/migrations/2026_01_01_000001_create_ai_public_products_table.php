<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_public_products', function (Blueprint $table) {
            // Core identity
            $table->id();
            $table->string('feed_product_id')->unique()->index();

            // Public catalog fields from Google Shopping feed
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('public_url');
            $table->string('image_url')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('availability', 50)->default('out of stock');
            $table->string('brand', 100)->nullable();
            $table->string('product_type', 255)->nullable();
            $table->string('google_product_category', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('material', 100)->nullable();
            $table->string('condition', 20)->default('new');

            // AI advisor enrichment tags
            $table->string('frame_shape', 50)->nullable()->index();
            $table->string('frame_material', 50)->nullable()->index();
            $table->string('frame_size_category', 20)->nullable()->index(); // small, medium, large, x-large
            $table->json('style_tags')->nullable();                          // ['sport', 'kids', 'minimalist', ...]
            $table->boolean('lightweight')->default(false)->index();
            $table->boolean('progressive_friendly')->default(false)->index();
            $table->boolean('strong_rx_friendly')->default(false)->index();
            $table->boolean('smart_glasses_relevant')->default(false)->index();
            $table->boolean('blue_light_relevant')->default(false)->index();
            $table->string('budget_tier', 20)->nullable()->index();          // budget, mid, premium

            // Catalog control
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_recommendable')->default(false)->index();
            $table->timestamp('last_seen_in_feed')->nullable();

            $table->timestamps();

            $table->index(['is_recommendable', 'is_active', 'availability']);
            $table->index(['frame_shape', 'frame_material', 'frame_size_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_public_products');
    }
};
