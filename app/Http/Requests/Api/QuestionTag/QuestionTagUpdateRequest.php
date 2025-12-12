<?php

namespace App\Http\Requests\Api\QuestionTag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class QuestionTagUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 教師 or 管理者のみタグマスタ編集を許可
        return in_array($this->user()?->role, ['teacher', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
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
