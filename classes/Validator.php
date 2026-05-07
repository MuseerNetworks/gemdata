<?php

declare(strict_types=1);

namespace GemData\Classes;

class Validator
{
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach ($fieldRules as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = 'This field is required.';
                }
                if ($value === null || $value === '') {
                    continue;
                }
                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'Enter a valid email address.';
                }
                if ($rule === 'numeric' && !is_numeric($value)) {
                    $errors[$field][] = 'Enter a numeric value.';
                }
                if (str_starts_with($rule, 'min:') && is_numeric($value) && (float) $value < (float) substr($rule, 4)) {
                    $errors[$field][] = 'Value is below the minimum allowed.';
                }
                if (str_starts_with($rule, 'max:') && is_numeric($value) && (float) $value > (float) substr($rule, 4)) {
                    $errors[$field][] = 'Value is above the maximum allowed.';
                }
                if ($rule === 'phone' && !preg_match('/^[0-9]{10,15}$/', (string) $value)) {
                    $errors[$field][] = 'Enter a valid phone number.';
                }
                if (str_starts_with($rule, 'minlen:') && strlen((string) $value) < (int) substr($rule, 7)) {
                    $errors[$field][] = 'Value is too short.';
                }
            }
        }
        return $errors;
    }
}
