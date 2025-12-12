<?php

namespace App\Http\Requests\Api\Progress;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProgressRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // auth.jwt + role:student が付いている前提なので true でOK
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allowed = ['course_id'];
            $extra   = collect($this->query())->keys()->diff($allowed);

            if ($extra->isNotEmpty()) {
                $validator->errors()->add(
                    'query',
                    __('api.common.validation.unexpected_parameter')
                );
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        // 設計書のRes（400）形式に合わせる
        throw new HttpResponseException(
            response()->json([
                'message' => __('api.auth.messages.validation_error'),
                'errors'  => $validator->errors(),
            ], 400)
        );
    }
}
