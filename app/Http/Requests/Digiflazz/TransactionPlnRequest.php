<?php

namespace App\Http\Requests\Digiflazz;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class TransactionPlnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'buyer_sku_code' => ['required', 'string'],
            'customer_no' => ['required', 'string'],
            'ref_id' => ['required', 'string'],
            'sign' => ['required', 'string'],
            'commands' => ['nullable', 'string'],
            'testing' => ['nullable', 'boolean'],
            'callback_url' => ['nullable', 'url'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'data' => [
                'ref_id' => $this->input('ref_id', ''),
                'customer_no' => $this->input('customer_no', ''),
                'buyer_sku_code' => $this->input('buyer_sku_code', ''),
                'message' => $validator->errors()->first(),
                'status' => 'Gagal',
                'rc' => '02',
            ],
        ], 200));
    }
}
