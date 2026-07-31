<?php
/**
 * Validator Class
 * Input validation with error handling
 */

class Validator {
    private $data = [];
    private $errors = [];
    private $rules = [];

    public function __construct($data = []) {
        $this->data = $data;
    }

    /**
     * Validate required field
     */
    public function required($fields) {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
                $this->errors[$field][] = ucfirst($field) . ' is required';
            }
        }

        return $this;
    }

    /**
     * Validate email
     */
    public function email($field) {
        if (isset($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field][] = 'Invalid email format';
            }
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function min($field, $length) {
        if (isset($this->data[$field])) {
            if (strlen($this->data[$field]) < $length) {
                $this->errors[$field][] = ucfirst($field) . " must be at least $length characters";
            }
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function max($field, $length) {
        if (isset($this->data[$field])) {
            if (strlen($this->data[$field]) > $length) {
                $this->errors[$field][] = ucfirst($field) . " must not exceed $length characters";
            }
        }
        return $this;
    }

    /**
     * Validate integer
     */
    public function integer($field) {
        if (isset($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
                $this->errors[$field][] = ucfirst($field) . ' must be an integer';
            }
        }
        return $this;
    }

    /**
     * Validate numeric range
     */
    public function range($field, $min, $max) {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!is_numeric($value) || $value < $min || $value > $max) {
                $this->errors[$field][] = ucfirst($field) . " must be between $min and $max";
            }
        }
        return $this;
    }

    /**
     * Validate URL
     */
    public function url($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
                $this->errors[$field][] = 'Invalid URL format';
            }
        }
        return $this;
    }

    /**
     * Validate phone number (basic)
     */
    public function phone($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = preg_replace('/[^0-9+]/', '', $this->data[$field]);
            if (strlen($phone) < 10) {
                $this->errors[$field][] = 'Invalid phone number';
            }
        }
        return $this;
    }

    /**
     * Validate confirmed field (e.g., password confirmation)
     */
    public function confirmed($field, $confirmField) {
        if (isset($this->data[$field]) && isset($this->data[$confirmField])) {
            if ($this->data[$field] !== $this->data[$confirmField]) {
                $this->errors[$field][] = ucfirst($field) . ' confirmation does not match';
            }
        }
        return $this;
    }

    /**
     * Validate unique in database
     */
    public function unique($field, $table, $column = null, $exceptId = null) {
        if (isset($this->data[$field])) {
            $column = $column ?? $field;
            $db = Database::getInstance();

            $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
            $params = [$this->data[$field]];

            if ($exceptId) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }

            $result = $db->fetchOne($sql, $params);
            if ($result['count'] > 0) {
                $this->errors[$field][] = ucfirst($field) . ' already exists';
            }
        }
        return $this;
    }

    /**
     * Custom validation rule
     */
    public function custom($field, $callback, $message = 'Invalid value') {
        if (isset($this->data[$field])) {
            if (!$callback($this->data[$field])) {
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function isValid() {
        return empty($this->errors);
    }

    /**
     * Get all errors
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function firstError($field) {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get validated data
     */
    public function validated() {
        return $this->data;
    }
}
