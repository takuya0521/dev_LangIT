<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tenant-test', function () {
    $dbName = DB::connection('tenant')->getDatabaseName();

    return 'Tenant DB: '.$dbName;
});
