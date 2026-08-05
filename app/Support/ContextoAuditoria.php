<?php

namespace App\Support;

class ContextoAuditoria
{
    public static ?string $justificativa = null;

    public static function definirJustificativa(?string $justificativa): void
    {
        self::$justificativa = $justificativa;
    }

    public static function limpar(): void
    {
        self::$justificativa = null;
    }
}
