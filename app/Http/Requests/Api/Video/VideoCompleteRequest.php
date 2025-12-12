<?php

namespace App\Http\Requests\Api\Video;

use Illuminate\Foundation\Http\FormRequest;

class VideoCompleteRequest extends FormRequest
{
    /**
     * 権限チェック
     * JWT で既に認証＆ロールチェックされているので true でOK
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     *
     * F03_02: watch_time
     * - 必須
     * - 整数
     * - 0以上
     */
    public function rules(): array
    {
        return [
            'watch_time' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * 属性名（エラーメッセージでの項目名）
     */
    public function attributes(): array
    {
        return [
            'watch_time' => '視聴時間',
        ];
    }
}
