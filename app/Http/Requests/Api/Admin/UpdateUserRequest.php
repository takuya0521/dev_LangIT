<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ロールチェックはミドルウェア（auth.jwt + role:admin）に任せる
        return true;
    }

    public function rules(): array
    {
        // ルートパラメータ {id} から対象ユーザーIDを取得
        $userId = $this->route('id');

        return [
            'email' => [
                'required',
                'string',
                'email:rlx',
                'max:255',
                'lowercase',
                // 自分以外のユーザーと重複していないこと
                Rule::unique(User::class, 'email')->ignore($userId),
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
            'status' => [
                'required',
                'in:active,inactive,suspended',
            ],
        ];
    }

    /**
     * バリデーション失敗時のレスポンス（400）
     * メッセージは lang/ja/api.php の api.auth.messages.validation_error を使用
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'message' => __('api.auth.messages.validation_error'),
            'errors'  => $validator->errors(),
        ], Response::HTTP_BAD_REQUEST);

        throw new ValidationException($validator, $response);
    }
}
