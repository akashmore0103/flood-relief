<?php
/**
 * Server-Side Data Validation Engine
 * Flood Risk Prediction & Relief Camp Locator
 */

class Validator {
    private array $errors = [];
    private array $data = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Validate data against specified rules
     * Rules format: ['field' => 'required|email|min:5|max:100']
     */
    public function validate(array $ruleSet): bool {
        $this->errors = [];

        foreach ($ruleSet as $field => $rules) {
            $ruleList = explode('|', $rules);
            $value = $this->data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                switch ($rule) {
                    case 'required':
                        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " is required.");
                        }
                        break;

                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be a valid number.");
                        }
                        break;

                    case 'email':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($field, "Please enter a valid email address.");
                        }
                        break;

                    case 'min':
                        if ($value !== null && strlen((string)$value) < (int)$params[0]) {
                            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least {$params[0]} characters.");
                        }
                        break;

                    case 'max':
                        if ($value !== null && strlen((string)$value) > (int)$params[0]) {
                            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " cannot exceed {$params[0]} characters.");
                        }
                        break;

                    case 'in':
                        if ($value !== null && !in_array($value, $params, true)) {
                            $this->addError($field, "Invalid selection for " . str_replace('_', ' ', $field) . ".");
                        }
                        break;

                    case 'coordinate_lat':
                        if ($value !== null && (!is_numeric($value) || $value < -90 || $value > 90)) {
                            $this->addError($field, "Latitude must be a valid number between -90 and 90.");
                        }
                        break;

                    case 'coordinate_lng':
                        if ($value !== null && (!is_numeric($value) || $value < -180 || $value > 180)) {
                            $this->addError($field, "Longitude must be a valid number between -180 and 180.");
                        }
                        break;
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Add an error for a field
     */
    public function addError(string $field, string $message): void {
        $this->errors[$field][] = $message;
    }

    /**
     * Get all validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Get the first error message as a single string
     */
    public function getFirstError(): ?string {
        if (empty($this->errors)) return null;
        $firstFieldErrors = reset($this->errors);
        return is_array($firstFieldErrors) ? reset($firstFieldErrors) : $firstFieldErrors;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }
}
