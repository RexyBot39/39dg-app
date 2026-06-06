<?php

use App\Models\AiPublicProduct;
use App\Services\AiAdvisor\FeedImporter;
use App\Services\AiAdvisor\ProductEnricher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// General web routes — 120 req/min per IP
Route::middleware(['throttle:web'])->group(function () {

    Route::get('/', function () {
        return view('welcome');
    });

    Route::get('/up', function () {
        return response()->json(['status' => 'ok', 'service' => '39DG Advisor']);
    })->withoutMiddleware(['throttle:web']);

    Route::get('/test/neurolux',     fn () => view('test.advisor', ['page' => 'neurolux',     'title' => 'Neurolux Lenses']));
    Route::get('/test/lumeo',        fn () => view('test.advisor', ['page' => 'lumeo',        'title' => 'Lumeo Smart Glasses']));
    Route::get('/test/blue495',      fn () => view('test.advisor', ['page' => 'blue495',      'title' => 'Blue495 Lenses']));
    Route::get('/test/progressives', fn () => view('test.advisor', ['page' => 'progressives', 'title' => 'Progressive Lenses']));
    Route::get('/test/frames',       fn () => view('test.advisor', ['page' => 'frames',       'title' => 'Eyeglass Frames']));
    Route::get('/test/lenses',       fn () => view('test.advisor', ['page' => 'lenses',       'title' => 'Lens Options']));

});

// One-time feed import trigger — protected by IMPORT_SECRET env var
Route::get('/run-import/{secret}', function (string $secret, FeedImporter $importer) {
    if ($secret !== env('IMPORT_SECRET') || !env('IMPORT_SECRET')) {
        abort(403);
    }
    set_time_limit(300);
    $log = $importer->run();
    return response()->json([
        'status'            => $log->status,
        'products_fetched'  => $log->products_fetched,
        'products_inserted' => $log->products_inserted,
        'products_updated'  => $log->products_updated,
        'duration_seconds'  => $log->duration_seconds,
        'warnings'          => $log->warnings,
    ]);
});

// Temporary catalog debug — protected by IMPORT_SECRET
Route::get('/debug-catalog/{secret}', function (string $secret) {
    if ($secret !== env('IMPORT_SECRET') || !env('IMPORT_SECRET')) {
        abort(403);
    }
    try {
        DB::connection()->getPdo();
        $dbStatus = 'connected';
        $dbName   = DB::connection()->getDatabaseName();
    } catch (\Throwable $e) {
        return response()->json(['db_error' => $e->getMessage()]);
    }
    $total         = AiPublicProduct::count();
    $active        = AiPublicProduct::where('is_active', true)->count();
    $recommendable = AiPublicProduct::where('is_recommendable', true)->count();
    $inStock       = AiPublicProduct::where('availability', 'in stock')->count();
    $hasUrl        = AiPublicProduct::whereNotNull('public_url')->where('public_url', '!=', '')->count();
    $hasPrice      = AiPublicProduct::whereNotNull('price')->where('price', '>', 0)->count();
    $sample        = AiPublicProduct::first();
    return response()->json(compact(
        'dbStatus', 'dbName', 'total', 'active', 'recommendable',
        'inStock', 'hasUrl', 'hasPrice', 'sample'
    ));
});

// AI enrichment — fills style_tags, frame_shape, frame_material via OpenAI vision
Route::get('/run-enrichment/{secret}', function (string $secret, ProductEnricher $enricher) {
    if ($secret !== env('IMPORT_SECRET') || !env('IMPORT_SECRET')) {
        abort(403);
    }
    set_time_limit(300);
    $batch  = (int) request('batch', 40);
    $result = $enricher->enrichBatch(max(1, min($batch, 100)));
    return response()->json($result);
});

// Reset enrichment
Route::get('/reset-enrichment/{secret}', function (string $secret) {
    if ($secret !== env('IMPORT_SECRET') || !env('IMPORT_SECRET')) {
        abort(403);
    }
    $count = AiPublicProduct::whereNotNull('ai_enriched_at')->update(['ai_enriched_at' => null]);
    return response()->json(['reset' => $count]);
});

// AI Advisor routes — 10 req/min per IP+session (protects OpenAI spend)
Route::prefix('advisor')->middleware(['throttle:chat'])->group(function () {

    Route::get('/', [\App\Http\Controllers\AdvisorController::class, 'index'])
        ->withoutMiddleware(['throttle:chat'])
        ->middleware(['throttle:web']);

    Route::post('/chat', [\App\Http\Controllers\AdvisorController::class, 'chat'])
        ->name('advisor.chat');

    Route::delete('/session', [\App\Http\Controllers\AdvisorController::class, 'clearSession'])
        ->name('advisor.session.clear');

});
