<?php

namespace App\Http\Requests\Digiflazz;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InquiryPlnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'customer_no' => ['required', 'string'],
            'sign' => ['required', 'string'],
            'ref_id' => ['nullable', 'string'],
            'buyer_sku_code' => ['nullable', 'string'],
            'commands' => ['nullable', 'string'],
            'testing' => ['nullable', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'data' => [
                'message' => $validator->errors()->first(),
                'status' => 'Gagal',
                'rc' => '02',
                'customer_no' => $this->input('customer_no', ''),
            ],
        ], 200));
    }
}
