<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 管理者ロールのチェックはミドルウェア（auth.jwt + role:admin）側で行う
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'min:8',
                'max:64',
                // 英字と数字を含み、ASCII印字可能文字のみ
                'regex:/^(?=.*[A-Za-z])(?=.*\d)[!-~]+$/',
            ],
            'password_confirmation' => [
                'required',
                'same:password',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'password'              => 'パスワード',
            'password_confirmation' => 'パスワード（確認）',
        ];
    }

    /**
     * バリデーション失敗時のレスポンス（400）
     * Login / StoreUser と同じ形式に揃える
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'message' => __('api.auth.messages.validation_error')
                ?? '入力内容に誤りがあります。',
            'errors'  => $validator->errors(),
        ], Response::HTTP_BAD_REQUEST); // 400

        throw new ValidationException($validator, $response);
    }
}
