<?php

namespace App\Http\Requests\Admin\Pages;

use App\Services\ComponentRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class AbstractPageVersionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $layout = $this->input('layout_json');

        if (is_string($layout)) {
            $decoded = json_decode($layout, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['layout_json' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function layoutRules(): array
    {
        return [
            'layout_json' => ['required', 'array', 'min:1'],
            'layout_json.*' => ['required', 'array'],
            'layout_json.*.type' => ['required', 'string'],
            'layout_json.*.props' => ['nullable', 'array'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->fails()) {
                return;
            }

            $layout = $this->input('layout_json', []);
            if (! is_array($layout)) {
                return;
            }

            $errors = app(ComponentRegistry::class)->validateLayout($layout);

            foreach ($errors as $key => $messages) {
                foreach ((array) $messages as $message) {
                    $validator->errors()->add($key, $message);
                }
            }
        });
    }
}

