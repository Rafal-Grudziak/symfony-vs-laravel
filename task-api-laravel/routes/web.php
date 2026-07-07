<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Task API is JSON-only. Use the /api routes (for example GET /api/projects).',
    ]);
});
