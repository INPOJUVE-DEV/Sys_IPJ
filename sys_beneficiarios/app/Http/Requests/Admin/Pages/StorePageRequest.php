<?php

namespace App\Http\Requests\Admin\Pages;

use App\Models\Page;
use Illuminate\Validation\Rule;

class StorePageRequest extends AbstractPageVersionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Page::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('pages', 'slug'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->layoutRules());
    }
}
