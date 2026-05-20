<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignDetail extends Model
{
    protected $fillable = [
        'campaign_id',
        'phone',
        'name',
        'message',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
