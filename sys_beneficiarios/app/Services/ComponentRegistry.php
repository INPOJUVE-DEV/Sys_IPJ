<?php

namespace App\Services;

use App\Models\ComponentCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ComponentRegistry
{
    public function registry(bool $onlyEnabled = true): Collection
    {
        $query = ComponentCatalog::query();

        if ($onlyEnabled) {
            $query->enabled();
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $layout
     * @throws ValidationException
     */
    public function assertValidLayout(array $layout): void
    {
        $errors = $this->validateLayout($layout);

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $layout
     * @return array<string, array<int, string>>
     */
    public function validateLayout(array $layout): array
    {
        $errors = [];
        $components = ComponentCatalog::query()->get()->keyBy('key');

        foreach ($layout as $index => $block) {
            $path = "layout_json.$index";

            if (! is_array($block)) {
                $errors[$path][] = 'Cada bloque debe ser un objeto con propiedades "type" y "props".';
                continue;
            }

            $type = $block['type'] ?? null;
            if (! is_string($type) || $type === '') {
                $errors["$path.type"][] = 'El componente requiere un identificador "type" válido.';
                continue;
            }

            $component = $components->get($type);
            if (! $component) {
                $errors["$path.type"][] = "El componente '$type' no existe en el catálogo.";
                continue;
            }

            if (! $component->enabled) {
                $errors["$path.type"][] = "El componente '$type' está deshabilitado.";
            }

            $schema = $component->schema ?? [];
            $props = $block['props'] ?? [];

            if (! empty($schema)) {
                $this->validateAgainstSchema(
                    $props,
                    $schema,
                    "$path.props",
                    $errors
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @param  array<string, mixed>  $schema
     * @param  string  $path
     * @param  array<string, array<int, string>>  $errors
     */
    protected function validateAgainstSchema($value, array $schema, string $path, array &$errors): void
    {
        $type = $schema['type'] ?? 'object';
        $nullable = $schema['nullable'] ?? false;

        if ($value === null) {
            if ($nullable) {
                return;
            }

            $errors[$path][] = 'Este componente requiere propiedades.';
            return;
        }

        switch ($type) {
            case 'object':
                if (! is_array($value)) {
                    $errors[$path][] = 'Debe ser un objeto.';
                    return;
                }

                $this->validateObject($value, $schema, $path, $errors);
                break;
            case 'array':
                if (! is_array($value)) {
                    $errors[$path][] = 'Debe ser un arreglo.';
                    return;
                }

                $this->validateArray($value, $schema, $path, $errors);
                break;
            case 'string':
                if (! is_string($value)) {
                    $errors[$path][] = 'Debe ser una cadena.';
                    return;
                }

                $this->validateString($value, $schema, $path, $errors);
                break;
            case 'integer':
                if (! is_int($value)) {
                    $errors[$path][] = 'Debe ser un entero.';
                    return;
                }

                $this->validateNumeric($value, $schema, $path, $errors);
                break;
            case 'number':
                if (! is_numeric($value)) {
                    $errors[$path][] = 'Debe ser un número.';
                    return;
                }

                $this->validateNumeric($value, $schema, $path, $errors);
                break;
            case 'boolean':
                if (! is_bool($value)) {
                    $errors[$path][] = 'Debe ser un valor booleano.';
                }
                break;
            default:
                // tipos no soportados se ignoran por ahora
                break;
        }
    }

    protected function validateObject(array $value, array $schema, string $path, array &$errors): void
    {
        $required = Arr::get($schema, 'required', []);
        foreach ($required as $property) {
            if (! Arr::has($value, $property)) {
                $errors["$path.$property"][] = "El campo '$property' es obligatorio.";
            }
        }

        $properties = Arr::get($schema, 'properties', []);
        foreach ($properties as $name => $definition) {
            $propertyPath = "$path.$name";
            $propertyValue = Arr::get($value, $name);

            if ($propertyValue === null && ($definition['nullable'] ?? false)) {
                continue;
            }

            if (! Arr::has($value, $name)) {
                continue;
            }

            $this->validateAgainstSchema($propertyValue, $definition, $propertyPath, $errors);
        }
    }

    protected function validateArray(array $value, array $schema, string $path, array &$errors): void
    {
        $min = $schema['min'] ?? null;
        $max = $schema['max'] ?? null;

        if ($min !== null && count($value) < $min) {
            $errors[$path][] = "Debe contener al menos $min elementos.";
        }

        if ($max !== null && count($value) > $max) {
            $errors[$path][] = "Debe contener máximo $max elementos.";
        }

        $itemsSchema = $schema['items'] ?? null;
        if ($itemsSchema) {
            foreach ($value as $index => $item) {
                $this->validateAgainstSchema($item, $itemsSchema, "$path.$index", $errors);
            }
        }
    }

    protected function validateString(string $value, array $schema, string $path, array &$errors): void
    {
        $min = $schema['min'] ?? null;
        $max = $schema['max'] ?? null;

        if ($min !== null && mb_strlen($value) < $min) {
            $errors[$path][] = "Debe contener al menos $min caracteres.";
        }

        if ($max !== null && mb_strlen($value) > $max) {
            $errors[$path][] = "Debe contener máximo $max caracteres.";
        }

        if (($schema['format'] ?? null) === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $errors[$path][] = 'Debe ser una URL válida.';
        }

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[$path][] = 'Valor no permitido para este campo.';
        }
    }

    protected function validateNumeric($value, array $schema, string $path, array &$errors): void
    {
        $min = $schema['min'] ?? null;
        $max = $schema['max'] ?? null;

        if ($min !== null && $value < $min) {
            $errors[$path][] = "Debe ser mayor o igual a $min.";
        }

        if ($max !== null && $value > $max) {
            $errors[$path][] = "Debe ser menor o igual a $max.";
        }
    }
}
