<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_public_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('lens_width_mm')->nullable()->after('frame_size_category');
            $table->unsignedSmallInteger('bridge_mm')->nullable()->after('lens_width_mm');
            $table->unsignedSmallInteger('temple_mm')->nullable()->after('bridge_mm');
            $table->unsignedSmallInteger('frame_height_mm')->nullable()->after('temple_mm');
        });
    }

    public function down(): void
    {
        Schema::table('ai_public_products', function (Blueprint $table) {
            $table->dropColumn(['lens_width_mm', 'bridge_mm', 'temple_mm', 'frame_height_mm']);
        });
    }
};
