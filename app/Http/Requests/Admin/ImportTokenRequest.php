<?php

namespace App\Http\Requests\Admin;

use App\Support\HrisPermission;
use Illuminate\Foundation\Http\FormRequest;

class ImportTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(HrisPermission::KARYAWAN_IMPORT) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'token' => 'required|uuid',
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Token sesi impor wajib disertakan.',
            'token.uuid' => 'Format token sesi impor tidak valid.',
        ];
    }
}
