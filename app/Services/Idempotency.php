<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Idempotency
{
    public static function key(array $data): string
    {
        $key = Str::lower(trim((string) ($data['idempotency_key'] ?? Str::uuid())));

        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('The operation key must be a valid UUID.'),
            ]);
        }

        return $key;
    }

    public static function payloadHash(array $context, array $data): string
    {
        unset($data['idempotency_key']);

        return hash('sha256', json_encode(
            self::canonicalize([
                'context' => $context,
                'payload' => $data,
            ]),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    public static function assertPayloadMatches(?string $storedHash, string $payloadHash): void
    {
        if ($storedHash === null || ! hash_equals($storedHash, $payloadHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('This operation key was already used with different data.'),
            ]);
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
