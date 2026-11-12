<?php

// 1. Helper Validation Functions (Returns error string, or null if valid)
function validateRequired(string $val, string $field): ?string {
    return empty(trim($val)) ? "$field is required." : null;
}

function validateEmailFormat(string $email): ?string {
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? null : "Invalid email format.";
}

function validateIntRange(int|string $val, string $field, int $min, int $max): ?string {
    return ($val >= $min && $val <= $max) ? null : "$field must be between $min and $max.";
}

function validatePhoneNumber(string $phone): ?string {
    return preg_match('/^09\d{9}$/', $phone) ? null : "Phone number must start with 09 and be exactly 11 digits.";
}

function validatePasswordStrength(string $val): ?string {
    if (strlen($val) < 8) return "Password must be at least 8 characters.";
    return null;
}

function validateFormSecurityToken(?string $submittedToken, ?string $sessionToken): ?string {
    if (!$submittedToken || !$sessionToken || !hash_equals($sessionToken, $submittedToken)) {
        return "Security verification failed. Please refresh the page.";
    }
    return null;
}

// 2. Main Validation Pipeline (Strict Top-to-Bottom Order)
function validateStudentInput(array $post): array {
    $username = trim($post['username'] ?? '');
    $email    = trim($post['email'] ?? '');
    $age      = trim($post['age'] ?? '');
    $phone    = trim($post['phone'] ?? '');
    $password = $post['password'] ?? '';

    // Evaluated strictly from top to bottom
    $errors = array_values(array_filter([
        validateRequired($username, 'Username'),
        validateRequired($email, 'Email Address'),
        validateEmailFormat($email),
        validateIntRange($age, 'Age', 1, 120),
        validateRequired($phone, 'Phone Number'),
        validatePhoneNumber($phone),
        validateRequired($password, 'Password'),
        validatePasswordStrength($password)
    ]));

    // If no errors exist, sanitize and return clean data
    if (empty($errors)) {
        return [
            'success' => true,
            'data' => [
                'username' => htmlspecialchars($username),
                'email'    => $email,
                'age'      => (int) $age,
                'phone'    => $phone
            ]
        ];
    }

    // Return the list of error messages in top-to-bottom order
    return [
        'success' => false,
        'errors'  => $errors
    ];
}