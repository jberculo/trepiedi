<?php

namespace App\Notice;

/**
 * Type van de per-account beheer-melding. Bepaalt de kleur van de banner:
 * info = groen, warning = oranje, error = rood.
 */
enum NoticeType: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';

    /**
     * De Bootstrap-alertvariant bij dit type.
     */
    public function bootstrapClass(): string
    {
        return match ($this) {
            self::Info => 'success',
            self::Warning => 'warning',
            self::Error => 'danger',
        };
    }

    /**
     * Vertaalsleutel voor het label in het beheerformulier.
     */
    public function label(): string
    {
        return 'admin.notice_type_' . $this->value;
    }
}
