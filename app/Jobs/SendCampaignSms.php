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
        $this->campaign->update(['status' => 'running']);

        $sent = 0;

        $this->campaign->details()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->each(function (CampaignDetail $detail) use (&$sent) {
                try {
                    // Simular envío con estado aleatorio
                    $success = (bool) rand(0, 9) > 1; // 80% éxito, 20% fallo

                    Log::info("SMS simulado", [
                        'phone'   => $detail->phone,
                        'name'    => $detail->name,
                        'message' => $detail->message,
                        'result'  => $success ? 'sent' : 'failed',
                    ]);

                    $detail->update([
                        'status'  => $success ? 'sent' : 'failed',
                        'sent_at' => $success ? now() : null,
                    ]);

                    if ($success) $sent++;

                } catch (\Exception $e) {
                    $detail->update(['status' => 'failed']);
                    Log::error("Error enviando SMS a {$detail->phone}: {$e->getMessage()}");
                }
            });

        $this->campaign->update([
            'status'     => 'completed',
            'sent_count' => $sent,
        ]);
    }
}
