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

        $sent  = 0;
        $limit = 10;

        // Enviar los primeros 10
        $this->campaign->details()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (CampaignDetail $detail) use (&$sent) {
                try {
                    // Aquí irá la integración real con proveedor SMS
                    // Por ahora simulamos el envío
                    Log::info("SMS enviado", [
                        'phone'   => $detail->phone,
                        'name'    => $detail->name,
                        'message' => $detail->message,
                    ]);

                    $detail->update([
                        'status'  => 'sent',
                        'sent_at' => now(),
                    ]);

                    $sent++;
                } catch (\Exception $e) {
                    $detail->update(['status' => 'failed']);
                    Log::error("Error enviando SMS a {$detail->phone}: {$e->getMessage()}");
                }
            });

        // Marcar el resto como simulados
        $this->campaign->details()
            ->where('status', 'pending')
            ->update(['status' => 'simulated']);

        $this->campaign->update([
            'status'     => 'completed',
            'sent_count' => $sent,
        ]);
    }
}
