<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignDetail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessCampaignCsv implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Campaign $campaign,
        public string $filePath,
        public string $separator = ','
    ) {}

    public function handle(): void
    {
        Log::info("ProcessCampaignCsv iniciado", ['campaign_id' => $this->campaign->id]);
        $this->campaign->update(['status' => 'processing']);

        $file      = fopen($this->filePath, 'r');
        $header    = fgetcsv($file, 0, $this->separator);
        $totalRows = 0;
        $batch     = [];

        while (($row = fgetcsv($file, 0, $this->separator)) !== false) {
            $data = array_combine($header, $row);

            $batch[] = [
                'campaign_id' => $this->campaign->id,
                'phone'       => $data['phone'] ?? $row[0],
                'name'        => $data['name']  ?? $row[1] ?? null,
                'message'     => isset($data['message'])
                    ? $data['message']
                    : str_replace('{name}', $data['name'] ?? '', $this->campaign->message),
                'status'      => 'pending',
                'created_at'  => now(),
                'updated_at'  => now(),
            ];

            $totalRows++;

            if (count($batch) === 500) {
                CampaignDetail::insertOrIgnore($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            CampaignDetail::insertOrIgnore($batch);
        }

        fclose($file);

        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }

        $realInserted = CampaignDetail::where('campaign_id', $this->campaign->id)->count();

        Log::info("Conteo final", [
            'totalRows'    => $totalRows,
            'realInserted' => $realInserted,
            'duplicates'   => $totalRows - $realInserted,
        ]);

        $this->campaign->update([
            'status'          => 'scheduled',
            'total_contacts'  => $totalRows,
            'duplicate_count' => $totalRows - $realInserted,
        ]);

        SendCampaignSms::dispatch($this->campaign)
            ->delay($this->campaign->scheduled_at);
    }
}
