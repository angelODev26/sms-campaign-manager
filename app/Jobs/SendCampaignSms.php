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
                    $success = rand(1, 10) <= 8; // 80% éxito, 20% fallo

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

        Log::info("Conteo ", [
            'Enviados'    => $sent
        ]);

        $this->campaign->update([
            'status'     => 'completed',
            'sent_count' => $sent,
        ]);
    }
}
