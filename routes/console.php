<?php

use App\Jobs\SendCampaignSms;
use App\Models\Campaign;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Campaign::where('status', 'scheduled')
        ->where('scheduled_at', '<=', now())
        ->get()
        ->each(function (Campaign $campaign) {
            SendCampaignSms::dispatch($campaign);
        });
})->everyMinute();
