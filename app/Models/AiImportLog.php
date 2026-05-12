<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiImportLog extends Model
{
    protected $table = 'ai_import_logs';

    protected $fillable = [
        'feed_url',
        'status',
        'products_fetched',
        'products_inserted',
        'products_updated',
        'products_deactivated',
        'products_skipped',
        'error_message',
        'warnings',
        'duration_seconds',
    ];

    protected $casts = [
        'warnings' => 'array',
    ];
}
