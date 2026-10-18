<?php

use App\Jobs\ExpireListings;
use App\Jobs\SendSavedSearchAlerts;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireListings)->hourly();
Schedule::job(new SendSavedSearchAlerts)->dailyAt('07:00');
