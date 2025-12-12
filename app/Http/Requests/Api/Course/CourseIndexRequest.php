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
        // 画面入力なし。基本的に何も受け取らない。
        return [];
    }

    protected function prepareForValidation(): void
    {
        // 特に変換なしでもOK
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // 許可するクエリパラメータ（現状なし）
            $allowed = [];
            $extra   = collect($this->query())->keys()->diff($allowed);

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
        // Res（400）：「不正なリクエストです。」＋ errors
        throw new HttpResponseException(
            response()->json([
                'message' => __('api.course.messages.invalid_request'),
                'errors'  => $validator->errors(),
            ], 400)
        );
    }
}
