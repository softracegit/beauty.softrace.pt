<?php

namespace App\Support;

/**
 * Ícones Phosphor disponíveis para tipos de tempo pessoal.
 */
final class PersonalTimeTypeIcons
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'ph-cooking-pot', 'label' => 'Refeição'],
            ['value' => 'ph-fork-knife', 'label' => 'Refeição (talheres)'],
            ['value' => 'ph-coffee', 'label' => 'Café'],
            ['value' => 'ph-users-three', 'label' => 'Reunião'],
            ['value' => 'ph-chats-circle', 'label' => 'Conversa'],
            ['value' => 'ph-calendar-check', 'label' => 'Consulta'],
            ['value' => 'ph-user', 'label' => 'Pessoal'],
            ['value' => 'ph-house', 'label' => 'Casa'],
            ['value' => 'ph-car', 'label' => 'Deslocação'],
            ['value' => 'ph-bicycle', 'label' => 'Bicicleta'],
            ['value' => 'ph-airplane-tilt', 'label' => 'Viagem'],
            ['value' => 'ph-briefcase', 'label' => 'Trabalho'],
            ['value' => 'ph-graduation-cap', 'label' => 'Formação'],
            ['value' => 'ph-book-open', 'label' => 'Estudo'],
            ['value' => 'ph-heartbeat', 'label' => 'Saúde'],
            ['value' => 'ph-first-aid', 'label' => 'Médico'],
            ['value' => 'ph-dumbbell', 'label' => 'Treino'],
            ['value' => 'ph-person-simple-walk', 'label' => 'Passeio'],
            ['value' => 'ph-phone', 'label' => 'Telefone'],
            ['value' => 'ph-envelope-simple', 'label' => 'Email'],
            ['value' => 'ph-clock', 'label' => 'Relógio'],
            ['value' => 'ph-moon', 'label' => 'Descanso'],
            ['value' => 'ph-sun', 'label' => 'Sol'],
            ['value' => 'ph-star', 'label' => 'Destaque'],
            ['value' => 'ph-pencil', 'label' => 'Outro / personalizado'],
            ['value' => 'ph-dots-three', 'label' => 'Outros'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (array $o) => $o['value'], self::options());
    }

    public static function isValid(string $icon): bool
    {
        return in_array($icon, self::values(), true);
    }
}
