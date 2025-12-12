<?php

namespace App\Http\Requests\Api\Test;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TestScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'student';
    }

    public function rules(): array
    {
        return [
            'answers'               => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.choice_id'   => ['required', 'integer'],
            'mode'             => ['sometimes', 'in:normal,review'],
            'elapsed_seconds'  => ['sometimes', 'integer', 'min:0'],
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
