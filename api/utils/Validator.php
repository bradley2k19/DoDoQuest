<?php
/**
 * Validator Utility Class
 * Input validation helpers
 */

class Validator {
    
    private $errors = [];
    
    /**
     * Validate required field
     */
    public function required($field, $value, $fieldName = null) {
        $name = $fieldName ?? $field;
        
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->errors[$field] = "$name is required.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate email
     */
    public function email($field, $value, $fieldName = null) {
        $name = $fieldName ?? $field;
        
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$name must be a valid email address.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($field, $value, $min, $fieldName = null) {
        $name = $fieldName ?? $field;
        
        if (!empty($value) && strlen($value) < $min) {
            $this->errors[$field] = "$name must be at least $min characters.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($field, $value, $max, $fieldName = null) {
        $name = $fieldName ?? $field;
        
        if (!empty($value) && strlen($value) > $max) {
            $this->errors[$field] = "$name must not exceed $max characters.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate numeric
     */
    public function numeric($field, $value, $fieldName = null) {
        $name = $fieldName ?? $field;
        
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field] = "$name must be a number.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate enum (must be one of allowed values)
     */
    public function enum($field, $value, $allowed, $fieldName = null) {
        $name = $fieldName ?? $field;
        
        if (!empty($value) && !in_array($value, $allowed)) {
            $this->errors[$field] = "$name must be one of: " . implode(', ', $allowed);
            return false;
        }
        return true;
    }
    
    /**
     * Validate latitude
     */
    public function latitude($field, $value, $fieldName = null) {
        $name = $fieldName ?? $field;
        
        if (!is_numeric($value) || $value < -90 || $value > 90) {
            $this->errors[$field] = "$name must be between -90 and 90.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate longitude
     */
    public function longitude($field, $value, $fieldName = null) {
        $name = $fieldName ?? $field;
        
        if (!is_numeric($value) || $value < -180 || $value > 180) {
            $this->errors[$field] = "$name must be between -180 and 180.";
            return false;
        }
        return true;
    }
    
    /**
     * Get all validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid() {
        return empty($this->errors);
    }
    
    /**
     * Reset errors
     */
    public function reset() {
        $this->errors = [];
    }
    
    /**
     * Sanitize string input
     */
    public static function sanitize($value) {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }
}
?>