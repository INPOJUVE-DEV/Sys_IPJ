<?php

namespace App\Http\Requests\Admin\Themes;

use App\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $tokens = $this->input('tokens');

        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['tokens' => $decoded]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('update', Theme::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'tokens' => ['required', 'array'],
            'tokens.colors' => ['required', 'array'],
            'tokens.colors.primary' => ['required', 'string', $this->hexColor()],
            'tokens.colors.secondary' => ['required', 'string', $this->hexColor()],
            'tokens.colors.background' => ['required', 'string', $this->hexColor()],
            'tokens.colors.surface' => ['required', 'string', $this->hexColor()],
            'tokens.colors.text' => ['required', 'string', $this->hexColor()],
            'tokens.typography' => ['required', 'array'],
            'tokens.typography.font_family' => ['required', 'string', 'max:255'],
            'tokens.typography.line_height' => ['required', 'numeric', 'between:1,2'],
            'tokens.typography.scale' => ['required', 'array'],
            'tokens.typography.scale.base' => ['required', 'string'],
            'tokens.spacing' => ['required', 'array'],
            'tokens.spacing.md' => ['required', 'numeric', 'min:0'],
            'tokens.radius' => ['sometimes', 'array'],
        ];
    }

    protected function hexColor(): Rule
    {
        return Rule::regex('/^#(?:[0-9a-fA-F]{3}){1,2}$/');
    }
}