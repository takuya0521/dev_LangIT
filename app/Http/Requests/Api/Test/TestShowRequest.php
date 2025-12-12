<?php

namespace App\Http\Requests\Api\Test;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TestShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'student';
    }

    public function rules(): array
    {
        // path の {test} はルート制約で numeric にする前提
        return [
            // 既存
            'random' => ['sometimes', 'boolean'],
            'limit'  => ['sometimes', 'integer', 'min:1'],

            // 誤答だけ再出題
            'only_incorrect' => ['sometimes', 'boolean'],

            // 難易度フィルタ（例: ?difficulty[]=easy&difficulty[]=hard）
            'difficulty'     => ['sometimes', 'array'],
            'difficulty.*'   => ['string', 'in:easy,normal,hard'],

            // タグフィルタ（例: ?tags[]=HTML&tags[]=リンク）
            'tags'           => ['sometimes', 'array'],
            'tags.*'         => ['string', 'max:255'],
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
