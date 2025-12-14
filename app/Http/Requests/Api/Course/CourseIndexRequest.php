<?php

namespace App\Http\Requests\Api\Course;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CourseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // auth.jwt + role:student が付いている前提なので true
        return true;
    }

    public function rules(): array
    {
        return [
            // 🔹キーワード検索（?keyword=HTML）
            'keyword' => ['nullable', 'string', 'max:100'],

            // 🔹学習ステータスフィルタ（?learning_status=in_progress など）
            'learning_status' => ['nullable', 'in:not_started,in_progress,completed'],

            // 🔹進捗率フィルタ（?min_progress=20&max_progress=80）
            'min_progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_progress' => ['nullable', 'integer', 'min:0', 'max:100', 'gte:min_progress'],

            // rules()
            'latest_only' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string'        => '検索キーワードは文字列で指定してください。',
            'learning_status.in'    => '学習ステータスの指定が不正です。',
            'min_progress.integer'  => '進捗率（最小値）は数値で指定してください。',
            'max_progress.integer'  => '進捗率（最大値）は数値で指定してください。',
            'max_progress.gte'      => '進捗率の最大値は最小値以上で指定してください。',
            'latest_only.boolean'   => '最新版フラグは true/false で指定してください。',
        ];
    }

    protected function prepareForValidation(): void
    {
        // 特に変換なしでもOK
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // ✅ 許可するクエリパラメータをちゃんと列挙する
            $allowed = [
                'keyword',
                'learning_status',
                'min_progress',
                'max_progress',
                'latest_only',
            ];

            $extra = collect($this->query())->keys()->diff($allowed);

            if ($extra->isNotEmpty()) {
                $validator->errors()->add(
                    'query',
                    __('api.common.messages.invalid_input')
                );
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => __('api.course.messages.invalid_request'),
                'errors'  => $validator->errors(),
            ], 400)
        );
    }
}
