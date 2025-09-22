<?php

namespace App\Http\Requests\Admin\Pages;

use App\Models\Page;

class RollbackPageRequest extends AbstractPageVersionRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            ? ($this->user()?->can('rollback', $page) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function layoutRules(): array
    {
        return [];
    }
}
