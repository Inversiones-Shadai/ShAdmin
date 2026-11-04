<?php

namespace App\Support;

final class ProductStatus
{
    public static function color(string $description): string
    {
        $status = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $description) ?? $description));

        return match ($status) {
            'ACTIVO' => 'green',
            'INACTIVO' => 'zinc',
            'AGOTADO' => 'amber',
            'FUERA DE MERCADO' => 'red',
            '' => 'zinc',
            default => 'blue',
        };
    }
}
