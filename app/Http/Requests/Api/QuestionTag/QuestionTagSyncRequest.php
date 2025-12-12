<?php

namespace App\Http\Requests\Api\QuestionTag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class QuestionTagSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 管理者のみ
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'tag_ids'   => ['required', 'array'],
            'tag_ids.*' => ['integer', 'distinct'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => __('api.common.validation_error'),
                'errors'  => $validator->errors(),
            ], 400)
        );
    }
}
