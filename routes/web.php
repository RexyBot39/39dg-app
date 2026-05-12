<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Advisor test pages — remove after testing
Route::get('/test/neurolux',    fn () => view('test.advisor', ['page' => 'neurolux',    'title' => 'Neurolux Lenses']));
Route::get('/test/lumeo',       fn () => view('test.advisor', ['page' => 'lumeo',       'title' => 'Lumeo Smart Glasses']));
Route::get('/test/blue495',     fn () => view('test.advisor', ['page' => 'blue495',     'title' => 'Blue495 Lenses']));
Route::get('/test/progressives',fn () => view('test.advisor', ['page' => 'progressives','title' => 'Progressive Lenses']));
Route::get('/test/frames',      fn () => view('test.advisor', ['page' => 'frames',      'title' => 'Eyeglass Frames']));
Route::get('/test/lenses',      fn () => view('test.advisor', ['page' => 'lenses',      'title' => 'Lens Options']));
