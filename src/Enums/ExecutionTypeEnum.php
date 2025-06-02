<?php

namespace OnaOnbir\OOAutoWeave\Enums;

enum ExecutionTypeEnum: string
{
    case DEFAULT = 'default'; // Her zaman çalışır
    case RULED = 'ruled';     // Koşula göre çalışır

    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => 'VARSAYILAN',
            self::RULED => 'KURALLI',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEFAULT => 'orange',
            self::RULED => 'red',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function optionsNested(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $select = [
                'label' => $case->label(),
                'value' => $case->value,
            ];
            $options[] = $select;
        }

        return $options;
    }

    public static function colors(): array
    {
        $colors = [];
        foreach (self::cases() as $case) {
            $colors[$case->value] = $case->color();
        }

        return $colors;
    }

    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    public static function labels(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = ucfirst(strtolower(str_replace('_', ' ', $case->name)));
        }

        return $array;
    }
}
