<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_advisor_logs', function (Blueprint $table) {
            $table->id();

            // Session (no user link — random UUID per browser session)
            $table->uuid('session_id')->nullable()->index();
            $table->string('ip_hash', 64)->nullable();   // sha256 of IP, not raw

            // Request context
            $table->string('page_context', 100)->nullable()->index();
            $table->text('question_text')->nullable();
            $table->boolean('pre_filtered')->default(false);  // blocked before OpenAI

            // Response
            $table->string('answer_type', 50)->nullable()->index();
            $table->boolean('support_handoff_triggered')->default(false)->index();
            $table->unsignedSmallInteger('products_recommended_count')->default(0);
            $table->json('products_recommended_ids')->nullable();

            // Performance
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();

            // Specialty brand interest (set when a brand knowledge file is loaded)
            $table->string('specialty_brand_interest', 50)->nullable()->index();

            // Lens category interest (set when a lens type file is loaded)
            $table->string('lens_category_interest', 50)->nullable()->index();

            $table->timestamps();

            $table->index(['created_at', 'answer_type']);
            $table->index(['page_context', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_advisor_logs');
    }
};
