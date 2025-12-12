<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestQuestion extends TenantModel
{
    use SoftDeletes;

    protected $table = 'test_questions';

    protected $fillable = [
        'test_id',
        'question_text',
        'explanation',
        'sort_order',
        'difficulty',
        'related_chapter_id',
        'related_video_id',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function choices()
    {
        return $this->hasMany(TestChoice::class, 'question_id');
    }

    public function relatedChapter()
    {
        return $this->belongsTo(Chapter::class, 'related_chapter_id');
    }

    public function relatedVideo()
    {
        return $this->belongsTo(Video::class, 'related_video_id');
    }

    /**
     * タグとの多対多
     * question_tag_pivot (question_id, tag_id) 経由
     */
    public function tags()
    {
        return $this->belongsToMany(
            QuestionTag::class,
            'question_tag_pivot',
            'question_id',
            'tag_id'
        );
    }
}
