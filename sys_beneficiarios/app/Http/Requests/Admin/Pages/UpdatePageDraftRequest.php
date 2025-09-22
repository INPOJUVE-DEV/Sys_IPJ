<?php

namespace App\Http\Requests\Admin\Pages;

use App\Models\Page;

class UpdatePageDraftRequest extends AbstractPageVersionRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            ? ($this->user()?->can('update', $page) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->layoutRules());
    }
}
