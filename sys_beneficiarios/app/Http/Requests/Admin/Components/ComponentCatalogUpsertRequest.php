<?php

namespace App\Http\Requests\Admin\Components;

use App\Models\ComponentCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComponentCatalogUpsertRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $schema = $this->input('schema');

        if (is_string($schema)) {
            $decoded = json_decode($schema, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['schema' => $decoded]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('upsert', ComponentCatalog::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_\-]+$/i'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'schema' => ['required', 'array'],
            'schema.type' => ['required', 'string', Rule::in(['object', 'array', 'string', 'integer', 'number', 'boolean'])],
            'schema.properties' => ['nullable', 'array'],
            'schema.items' => ['nullable', 'array'],
            'schema.required' => ['nullable', 'array'],
        ];
    }
}
