<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;

class Video extends TenantModel
{
    protected $table = 'videos';

    protected $fillable = [
        'chapter_id',
        'title',
        'video_url',
        'duration',
        'sort_order',
    ];

    /**
     * この動画が属するチャプター
     */
    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }
}
