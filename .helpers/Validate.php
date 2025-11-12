<?php
declare(strict_types=1);

final class Validate
{
    public static function name(?string $value): bool
    {
        $value = trim((string)$value);
        return $value !== '' && mb_strlen($value) >= 3 && !preg_match('/^\d+$/u', $value);
    }

    public static function email(?string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function seat(?string $value): bool
    {
        return (bool)preg_match('/^[A-Za-z]\d{4}$/', trim((string)$value));
    }

    public static function telNumber(?int $value): bool
    {
        if ($value === null) {
            return false;
        }

        return $value >= 100000 && $value <= 999999999999;
    }
}