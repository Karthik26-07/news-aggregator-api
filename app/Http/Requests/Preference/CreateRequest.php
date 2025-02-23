<?php

namespace App\Http\Requests\Preference;

use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preferred_sources' => 'nullable|string|max:255',
            'preferred_categories' => 'nullable|string|max:255',
            'preferred_authors' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'preferred_sources.string' => 'Preferred sources must be a string (e.g., "TechCrunch,BBC").',
            'preferred_categories.string' => 'Preferred categories must be a string (e.g., "tech,politics").',
            'preferred_authors.string' => 'Preferred authors must be a string (e.g., "Jane Doe,John Smith").',
            'preferred_sources.max' => 'Preferred sources cannot exceed 255 characters.',
            'preferred_categories.max' => 'Preferred categories cannot exceed 255 characters.',
            'preferred_authors.max' => 'Preferred authors cannot exceed 255 characters.',
        ];
    }
}
