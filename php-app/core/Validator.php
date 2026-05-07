<?php

namespace Core;

/**
 * Simple input validator.
 *
 * Usage:
 *   $v = new Validator($data);
 *   $v->required('email')->email('email')->minLength('password', 8);
 *   if ($v->fails()) { $errors = $v->errors(); }
 */
class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    // ── Rules ────────────────────────────────────────────────────────────────

    public function required(string $field, string $label = ''): static {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value === '' || $value === null) {
            $this->errors[$field][] = "{$label} is required.";
        }
        return $this;
    }

    public function email(string $field, string $label = ''): static {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "{$label} must be a valid email address.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): static {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && mb_strlen($value) < $min) {
            $this->errors[$field][] = "{$label} must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): static {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && mb_strlen($value) > $max) {
            $this->errors[$field][] = "{$label} must not exceed {$max} characters.";
        }
        return $this;
    }

    public function inList(string $field, array $allowed, string $label = ''): static {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field][] = "{$label} contains an invalid value.";
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): static {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field][] = "{$label} must be a number.";
        }
        return $this;
    }

    public function matches(string $field, string $otherField, string $label = ''): static {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value      = $this->data[$field] ?? '';
        $otherValue = $this->data[$otherField] ?? '';
        if ($value !== $otherValue) {
            $this->errors[$field][] = "{$label} does not match.";
        }
        return $this;
    }

    // ── Results ──────────────────────────────────────────────────────────────

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    /** Return all errors as a flat array of strings. */
    public function errors(): array {
        return $this->errors;
    }

    /** Return the first error for a given field, or null. */
    public function firstError(string $field): ?string {
        return $this->errors[$field][0] ?? null;
    }

    /** Return all errors as a single concatenated string. */
    public function errorString(string $separator = ' '): string {
        $flat = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $msg) {
                $flat[] = $msg;
            }
        }
        return implode($separator, $flat);
    }
}
