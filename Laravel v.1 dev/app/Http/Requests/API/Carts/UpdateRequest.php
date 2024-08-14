<?php

namespace App\Http\Requests\API\Carts;

use Illuminate\Foundation\Http\FormRequest;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Contracts\Validation\Validator;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $token = $this->bearerToken();
        return $token && JWTAuth::parseToken()->authenticate();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * Get the messages for the validation rules.
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'Kuantitas harus diisi',
            'quantity.integer' => 'Kuantitas harus berupa angka bulat',
            'quantity.min' => 'Kuantitas harus lebih besar dari 0',
        ];
    }

    /**
     * Configure the validator instance.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'status' => 'error',
            'messages' => count($validator->errors()->all()) > 1 ? $validator->errors()->all() : implode(' ', $validator->errors()->all()),
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}