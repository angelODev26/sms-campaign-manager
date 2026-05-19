<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignDetail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCampaignCsv implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Campaign $campaign,
        public string $filePath
    ) {}

    public function handle(): void
    {
        $this->campaign->update(['status' => 'processing']);

        $file      = fopen($this->filePath, 'r');
        $header    = fgetcsv($file);
        $totalRows = 0;
        $inserted  = 0;
        $batch     = [];

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            $batch[] = [
                'campaign_id' => $this->campaign->id,
                'phone'       => $data['phone'] ?? $row[0],
                'name'        => $data['name']  ?? $row[1] ?? null,
                'status'      => 'pending',
                'created_at'  => now(),
                'updated_at'  => now(),
            ];

            $totalRows++;

            if (count($batch) === 500) {
                $before    = CampaignDetail::where('campaign_id', $this->campaign->id)->count();
                CampaignDetail::insertOrIgnore($batch);
                $after     = CampaignDetail::where('campaign_id', $this->campaign->id)->count();
                $inserted += ($after - $before);
                $batch     = [];
            }
        }

        if (!empty($batch)) {
            $before    = CampaignDetail::where('campaign_id', $this->campaign->id)->count();
            CampaignDetail::insertOrIgnore($batch);
            $after     = CampaignDetail::where('campaign_id', $this->campaign->id)->count();
            $inserted += ($after - $before);
        }

        fclose($file);

        $this->campaign->update([
            'status'          => 'scheduled',
            'total_contacts'  => $totalRows,
            'duplicate_count' => $totalRows - $inserted,
        ]);
    }
}
