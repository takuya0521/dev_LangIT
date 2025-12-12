<?php

namespace App\Http\Requests\Api\Video;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class VideoEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event'    => ['required', 'string', 'in:play,pause,seek'],
            'position' => ['required', 'integer', 'min:0'], // 秒
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => '視聴データが不正です。',
                'errors'  => $validator->errors(),
            ], 400)
        );
    }
}
