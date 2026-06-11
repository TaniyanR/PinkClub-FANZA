<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    $known = $_SESSION['_csrf'] ?? '';
    return is_string($token) && $token !== '' && is_string($known) && $known !== '' && hash_equals($known, $token);
}

function csrf_validate_or_fail(?string $token): void
{
    if (!csrf_verify($token)) {
        http_response_code(419);
        exit('CSRF validation failed.');
    }
}
