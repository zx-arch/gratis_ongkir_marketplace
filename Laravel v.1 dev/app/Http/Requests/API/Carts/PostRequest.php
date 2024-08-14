<?php

namespace App\Http\Requests\API\Carts;

use Illuminate\Foundation\Http\FormRequest;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Contracts\Validation\Validator;

class PostRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data' => 'sometimes|required|array',
            'data.*.product_id' => 'required|integer|min:1|exists:products,id',
            'data.*.quantity' => 'sometimes|required|integer|min:1',
            'product_id' => 'required_if:data,null|integer|exists:products,id',
            'quantity' => 'required_if:data,null|integer|min:1',
        ];
    }

    /**
     * Get the messages rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */

    public function messages(): array
    {
        return [
            'data.required' => 'Data produk harus diisi',
            'data.array' => 'Data produk harus berupa array',
            'data.*.product_id.required' => 'ID produk harus diisi',
            'data.*.product_id.integer' => 'ID produk harus berupa angka bulat',
            'data.*.product_id.min' => 'ID produk harus lebih besar dari 0',
            'data.*.product_id.exists' => 'ID produk tidak ada dalam tabel produk',
            'data.*.quantity.required' => 'Kuantitas harus diisi',
            'data.*.quantity.integer' => 'Kuantitas harus berupa angka bulat',
            'data.*.quantity.min' => 'Kuantitas harus lebih besar dari 0',
            'product_id.required_if' => 'ID produk harus diisi jika data tidak ada',
            'product_id.integer' => 'ID produk harus berupa angka bulat',
            'product_id.exists' => 'ID produk tidak ada dalam tabel produk',
            'quantity.required_if' => 'Kuantitas harus diisi jika data tidak ada',
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