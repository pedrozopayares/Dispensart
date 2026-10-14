<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Bitácora de operaciones sensibles ampliada al ingreso de lote (S18, design D4): stock.received sobre el
| movimiento `entrada`. Reemplaza por nombre los tres CHECK, con el patrón de S10. No toca el CHECK de detalle
| solo numérico ni el disparador de solo inserción. down() repone los conjuntos de S10 con NOT VALID: las filas
| nuevas ya escritas no se pueden borrar, quedan, y un up() posterior valida de nuevo.
*/
return new class extends Migration
{
    private const PRIOR_ACTIONS = "'prescription.created', 'dispensation.created', 'controlled_drug.authorized', "
        ."'controlled_drug.authorization_failed', 'transfer.approved', 'transfer.voided', 'transfer.discrepancy_resolved', "
        ."'user.created', 'stock.adjusted'";

    private const SUBJECTS = "'prescription', 'dispensation', 'transfer', 'user', 'kardex_movement'";

    private const PRIOR_PAIRS = "(action IN ('prescription.created', 'controlled_drug.authorization_failed') AND subject_type = 'prescription')"
        ." OR (action IN ('dispensation.created', 'controlled_drug.authorized') AND subject_type = 'dispensation')"
        ." OR (action IN ('transfer.approved', 'transfer.voided', 'transfer.discrepancy_resolved') AND subject_type = 'transfer')"
        ." OR (action = 'user.created' AND subject_type = 'user')"
        ." OR (action = 'stock.adjusted' AND subject_type = 'kardex_movement')";

    public function up(): void
    {
        $this->replace(
            actions: 'action IN ('.self::PRIOR_ACTIONS.", 'stock.received')",
            subjects: 'subject_type IN ('.self::SUBJECTS.')',
            pairs: self::PRIOR_PAIRS." OR (action = 'stock.received' AND subject_type = 'kardex_movement')",
            validate: true,
        );
    }

    public function down(): void
    {
        $this->replace(
            actions: 'action IN ('.self::PRIOR_ACTIONS.')',
            subjects: 'subject_type IN ('.self::SUBJECTS.')',
            pairs: self::PRIOR_PAIRS,
            validate: false,
        );
    }

    private function replace(string $actions, string $subjects, string $pairs, bool $validate): void
    {
        $suffix = $validate ? '' : ' NOT VALID';
        $checks = [
            'audit_events_action_check' => $actions,
            'audit_events_subject_type_check' => $subjects,
            'audit_events_action_subject_check' => $pairs,
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE audit_events DROP CONSTRAINT {$name}");
            DB::statement("ALTER TABLE audit_events ADD CONSTRAINT {$name} CHECK ({$expression}){$suffix}");
        }
    }
};
