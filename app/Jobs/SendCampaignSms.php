<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignDetail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendCampaignSms implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Campaign $campaign
    ) {}

    public function handle(): void
    {

        // Evitar doble ejecución
        if ($this->campaign->status === 'completed') {
            return;
        }

        $this->campaign->refresh();
        $this->campaign->update(['status' => 'running']);

        $sent = 0;

        $this->campaign->details()
            ->where('status', 'pending')
            ->chunkById(500, function ($details) use (&$sent) {
                $sentIds   = [];
                $failedIds = [];

                foreach ($details as $detail) {
                    if (rand(1, 10) <= 8) {
                        $sentIds[] = $detail->id;
                        $sent++;
                    } else {
                        $failedIds[] = $detail->id;
                    }
                }

                if (!empty($sentIds)) {
                    CampaignDetail::whereIn('id', $sentIds)->update(['status' => 'sent', 'sent_at' => now()]);
                }
                if (!empty($failedIds)) {
                    CampaignDetail::whereIn('id', $failedIds)->update(['status' => 'failed']);
                }
            });
        $this->campaign->update([
            'status'     => 'completed',
            'sent_count' => $sent,
        ]);
    }
}
