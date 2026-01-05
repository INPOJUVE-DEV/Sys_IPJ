<?php

namespace App\Rules;

use App\Support\SeccionResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSeccional implements ValidationRule
{
    public function __construct(private readonly string $message = 'La seccional no existe en el catálogo')
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! SeccionResolver::resolve($value)) {
            $fail($this->message);
        }
    }
}
