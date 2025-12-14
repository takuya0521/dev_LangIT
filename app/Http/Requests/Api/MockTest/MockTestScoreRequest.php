<?php

namespace App\Http\Requests\Api\MockTest;

use Illuminate\Foundation\Http\FormRequest;

class MockTestScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required_with:answers', 'integer'],
            'answers.*.choice_id'   => ['required_with:answers', 'integer'],
        ];
    }
}