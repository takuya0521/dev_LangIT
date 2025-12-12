<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // JWT による認証に任せるので true
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:64',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)[!-~]+$/',
            ],
            'password_confirmation' => [
                'required',
                'same:password',
            ],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'message' => __('api.auth.messages.validation_error'),
            'errors'  => $validator->errors(),
        ], Response::HTTP_BAD_REQUEST); // 400

        throw new ValidationException($validator, $response);
    }
}
