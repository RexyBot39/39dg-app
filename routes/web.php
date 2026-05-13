<?php

use App\Services\AiAdvisor\FeedImporter;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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

// Advisor test pages — remove after testing
Route::get('/test/neurolux',    fn () => view('test.advisor', ['page' => 'neurolux',    'title' => 'Neurolux Lenses']));
Route::get('/test/lumeo',       fn () => view('test.advisor', ['page' => 'lumeo',       'title' => 'Lumeo Smart Glasses']));
Route::get('/test/blue495',     fn () => view('test.advisor', ['page' => 'blue495',     'title' => 'Blue495 Lenses']));
Route::get('/test/progressives',fn () => view('test.advisor', ['page' => 'progressives','title' => 'Progressive Lenses']));
Route::get('/test/frames',      fn () => view('test.advisor', ['page' => 'frames',      'title' => 'Eyeglass Frames']));
Route::get('/test/lenses',      fn () => view('test.advisor', ['page' => 'lenses',      'title' => 'Lens Options']));
