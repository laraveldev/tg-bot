<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
// app/Providers/RouteServiceProvider.php
use DefStudio\Telegraph\Facades\Telegraph;

class RouteServiceProvider extends ServiceProvider{
    public function boot()
    {
    parent::boot();

    Telegraph::webhook('/telegraph/{token}');
    Telegraph::routes('/my-webhook');
    }
}