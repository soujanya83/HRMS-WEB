<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $organizationId = $this->route('organization')->id;
        return [
            'name' => 'sometimes|required|string|max:255',
            'registration_number' => 'sometimes|required|string|max:255|unique:organizations,registration_number,' . $organizationId,
            'address' => 'nullable|string',
            'contact_email' => 'sometimes|required|email|max:255|unique:organizations,contact_email,' . $organizationId,
            'contact_phone' => 'nullable|string|max:20',
            'industry_type' => 'nullable|string|max:255',
            'logo_url' => 'nullable|url',
            'timezone' => 'nullable|string|max:255',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422));
    }
}
