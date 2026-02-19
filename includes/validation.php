<?php
/**
 * Input Validation Helpers — Sawari
 * 
 * Functions to validate user inputs before processing.
 * All functions return true on valid input, or an error message string on failure.
 */

/**
 * Validate an email address.
 * 
 * @param string $email
 * @return bool
 */
function validateEmail(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate a password.
 * Rules: minimum 8 characters, at least 1 letter, at least 1 number.
 * 
 * @param string $password
 * @return bool
 */
function validatePassword(string $password): bool
{
    if (strlen($password) < 8)
        return false;
    if (!preg_match('/[A-Za-z]/', $password))
        return false;
    if (!preg_match('/[0-9]/', $password))
        return false;
    return true;
}

/**
 * Validate a Nepali phone number.
 * Accepts formats: 98XXXXXXXX, 97XXXXXXXX, +977-98XXXXXXXX, etc.
 * 
 * @param string $phone
 * @return bool
 */
function validatePhone(string $phone): bool
{
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    // Nepali mobile: starts with 97 or 98, total 10 digits (without country code)
    // Or with +977 prefix
    return (bool) preg_match('/^(\+977)?9[78]\d{8}$/', $phone);
}

/**
 * Validate latitude value.
 * 
 * @param mixed $lat
 * @return bool
 */
function validateLatitude(mixed $lat): bool
{
    if (!is_numeric($lat))
        return false;
    $lat = (float) $lat;
    return $lat >= -90 && $lat <= 90;
}

/**
 * Validate longitude value.
 * 
 * @param mixed $lng
 * @return bool
 */
function validateLongitude(mixed $lng): bool
{
    if (!is_numeric($lng))
        return false;
    $lng = (float) $lng;
    return $lng >= -180 && $lng <= 180;
}

/**
 * Validate a rating value (1-5 integer).
 * 
 * @param mixed $rating
 * @return bool
 */
function validateRating(mixed $rating): bool
{
    if (!is_numeric($rating))
        return false;
    $rating = (int) $rating;
    return $rating >= 1 && $rating <= 5;
}

/**
 * Validate that required fields are present in a data array.
 * 
 * @param array $requiredFields Array of required field names
 * @param array $data           Data array to check
 * @return array                Array of missing field names (empty = all present)
 */
function validateRequired(array $requiredFields, array $data): array
{
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Validate an image upload.
 * Checks for errors, size, and MIME type.
 * 
 * @param array $file The $_FILES['field'] array
 * @return bool|string True if valid, error message string if invalid
 */
function validateImageUpload(array $file): bool|string
{
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server error: failed to write file.',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by server extension.',
        ];
        return $errorMessages[$file['error']] ?? 'Unknown upload error.';
    }

    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $maxMB = MAX_UPLOAD_SIZE / (1024 * 1024);
        return "File size exceeds the maximum allowed size of {$maxMB}MB.";
    }

    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return 'Invalid file type. Allowed types: JPEG, PNG, WebP.';
    }

    // Check extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
        return 'Invalid file extension. Allowed: jpg, jpeg, png, webp.';
    }

    return true;
}

/**
 * Validate a time string (HH:MM format).
 * 
 * @param string $time
 * @return bool
 */
function validateTime(string $time): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time);
}

/**
 * Validate that a value is in an allowed enum list.
 * 
 * @param string $value   Value to check
 * @param array  $allowed Allowed values
 * @return bool
 */
function validateEnum(string $value, array $allowed): bool
{
    return in_array($value, $allowed, true);
}

/**
 * Validate and sanitize a JSON string.
 * 
 * @param string $json
 * @return array|false Decoded array on success, false on invalid JSON
 */
function validateJSON(string $json): array|false
{
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    return $data;
}
