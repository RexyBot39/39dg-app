<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feed_url');
            $table->string('status', 20)->index(); // success, partial, failed
            $table->integer('products_fetched')->default(0);
            $table->integer('products_inserted')->default(0);
            $table->integer('products_updated')->default(0);
            $table->integer('products_deactivated')->default(0);
            $table->integer('products_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->json('warnings')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_import_logs');
    }
};
