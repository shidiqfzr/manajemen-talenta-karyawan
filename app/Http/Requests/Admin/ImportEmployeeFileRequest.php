<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportEmployeeFileRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'import_file' => 'required|file|mimes:xlsx,xls|max:10240',
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     */
    public function messages(): array
    {
        return [
            'import_file.required' => 'Berkas spreadsheet pegawai wajib diunggah.',
            'import_file.file' => 'Berkas yang diunggah harus berupa berkas valid.',
            'import_file.mimes' => 'Format berkas harus berupa spreadsheet Excel (.xlsx atau .xls).',
            'import_file.max' => 'Ukuran berkas spreadsheet maksimal 10 MB.',
        ];
    }
}
