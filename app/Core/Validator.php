<?php

namespace App\Core;

class Validator
{
    private $data;
    private $rules;
    private $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $rules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $ruleName = $rule;
                $ruleParam = null;

                if (str_contains($rule, ':')) {
                    $parts = explode(':', $rule, 2);
                    $ruleName = $parts[0];
                    $ruleParam = $parts[1];
                }

                $this->applyRule($field, $value, $ruleName, $ruleParam);
            }
        }

        return empty($this->errors);
    }

    private function applyRule(string $field, $value, string $rule, ?string $param): void
    {
        $fieldName = $this->getFieldName($field);

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    $this->errors[$field] = "{$fieldName} is required";
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = "{$fieldName} must be a valid email address";
                }
                break;

            case 'min':
                if ($value !== null && mb_strlen((string)$value) < (int)$param) {
                    $this->errors[$field] = "{$fieldName} must be at least {$param} characters";
                }
                break;

            case 'max':
                if ($value !== null && mb_strlen((string)$value) > (int)$param) {
                    $this->errors[$field] = "{$fieldName} must not exceed {$param} characters";
                }
                break;

            case 'alpha_num':
                if ($value !== null && !preg_match('/^[a-zA-Z0-9_]+$/', (string)$value)) {
                    $this->errors[$field] = "{$fieldName} must contain only alphanumeric characters and underscores";
                }
                break;

            case 'in':
                $allowed = explode(',', $param);
                if ($value !== null && !in_array($value, $allowed)) {
                    $this->errors[$field] = "{$fieldName} must be one of: " . implode(', ', $allowed);
                }
                break;

            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->errors[$field] = "{$fieldName} must be a number";
                }
                break;

            case 'confirmed':
                $confirmValue = $this->data[$field . '_confirmation'] ?? null;
                if ($value !== $confirmValue) {
                    $this->errors[$field] = "{$fieldName} confirmation does not match";
                }
                break;
        }
    }

    private function getFieldName(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    public function validated(): array
    {
        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }
        return $validated;
    }
}
