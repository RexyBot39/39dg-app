<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAdvisorLog extends Model
{
    protected $table = 'ai_advisor_logs';

    protected $fillable = [
        'session_id',
        'ip_hash',
        'page_context',
        'question_text',
        'pre_filtered',
        'answer_type',
        'support_handoff_triggered',
        'products_recommended_count',
        'products_recommended_ids',
        'response_time_ms',
        'tokens_used',
        'specialty_brand_interest',
        'lens_category_interest',
    ];

    protected $casts = [
        'pre_filtered'               => 'boolean',
        'support_handoff_triggered'  => 'boolean',
        'products_recommended_ids'   => 'array',
    ];
}
