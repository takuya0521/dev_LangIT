<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ロールチェックはミドルウェア側で行うので true
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rlx',
                'max:255',
                'lowercase',
                // テナントDBの users テーブルでユニーク
                Rule::unique(User::class, 'email'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'role' => [
                'required',
                'in:student,teacher,admin',
            ],
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
            'email'                 => 'メールアドレス',
            'name'                  => '氏名',
            'role'                  => 'ロール',
            'password'              => 'パスワード',
            'password_confirmation' => 'パスワード（確認）',
        ];
    }

    /**
     * バリデーション失敗時のレスポンスを Login と揃える（400）
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'message' => __('api.auth.messages.validation_error'),
            'errors'  => $validator->errors(),
        ], Response::HTTP_BAD_REQUEST); // 400

        throw new ValidationException($validator, $response);
    }
}
