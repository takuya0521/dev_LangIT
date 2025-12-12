<?php

namespace App\Http\Requests\Api\Video;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class VideoShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator)
    {
        // 許可するクエリパラメータは video_id のみ
        $validator->after(function ($validator) {
            $allowed = ['video_id'];
            $extra   = collect($this->query())->keys()->diff($allowed);

            if ($extra->isNotEmpty()) {
                $validator->errors()->add('query', '不正なリクエストです。');
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => '不正なリクエストです。',
                'errors'  => $validator->errors(),
            ], 400)
        );
    }
}
