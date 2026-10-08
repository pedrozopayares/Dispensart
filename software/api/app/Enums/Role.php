<?php

namespace App\Enums;

/**
 * Roles de FARTMAR IPS (§ 3). El mapa rol → capacidades vive solo aquí, con denegación por defecto.
 */
enum Role: string
{
    case AuxiliarFarmacia = 'auxiliar_farmacia';
    case RegenteFarmacia = 'regente_farmacia';
    case Medico = 'medico';
    case Auditor = 'auditor';
    case Admin = 'admin';

    /**
     * Capacidades exactas del rol según § 3.
     *
     * @return list<Ability>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::AuxiliarFarmacia => self::pharmacyBase(),
            self::RegenteFarmacia => [
                ...self::pharmacyBase(),
                Ability::TransfersApprove,
                Ability::ControlledDrugsAuthorize,
                Ability::InventoryAdjust,
            ],
            self::Medico => [Ability::CatalogView, Ability::PrescriptionsCreate, Ability::PatientsView],
            self::Auditor => [Ability::CatalogView, Ability::InventoryView, Ability::TransfersView, Ability::PatientsView],
            self::Admin => [Ability::CatalogView, Ability::CatalogManage, Ability::UsersManage],
        };
    }

    /**
     * Denegación por defecto: una capacidad desconocida o ausente del mapa del rol se niega.
     */
    public function allows(string $ability): bool
    {
        $known = Ability::tryFrom($ability);

        return $known !== null && in_array($known, $this->abilities(), true);
    }

    /**
     * Valores de capacidad del rol, como los recibe la SPA.
     *
     * @return list<string>
     */
    public function abilityValues(): array
    {
        return array_map(fn (Ability $ability): string => $ability->value, $this->abilities());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }

    /**
     * Capacidades comunes del personal de farmacia (auxiliar; el regente las amplía).
     *
     * @return list<Ability>
     */
    private static function pharmacyBase(): array
    {
        return [
            Ability::CatalogView,
            Ability::InventoryView,
            Ability::DispensationsCreate,
            Ability::TransfersView,
            Ability::TransfersCreate,
            Ability::TransfersReceive,
            Ability::PatientsView,
        ];
    }
}
