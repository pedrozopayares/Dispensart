<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Bitácora de operaciones sensibles (S3 + traslados) ampliada al alta de usuarios y al ajuste manual de stock
| (S10, design D5): user.created sobre el usuario, stock.adjusted sobre el movimiento `ajuste`. Reemplaza por
| nombre los tres CHECK. No toca el CHECK de detalle solo numérico ni el disparador de solo inserción.
| down() repone los conjuntos de S3 + traslados con NOT VALID: las filas nuevas ya escritas no se pueden borrar,
| quedan, y un up() posterior valida de nuevo.
*/
return new class extends Migration
{
    private const PRIOR_ACTIONS = "'prescription.created', 'dispensation.created', 'controlled_drug.authorized', "
        ."'controlled_drug.authorization_failed', 'transfer.approved', 'transfer.voided', 'transfer.discrepancy_resolved'";

    private const PRIOR_SUBJECTS = "'prescription', 'dispensation', 'transfer'";

    private const PRIOR_PAIRS = "(action IN ('prescription.created', 'controlled_drug.authorization_failed') AND subject_type = 'prescription')"
        ." OR (action IN ('dispensation.created', 'controlled_drug.authorized') AND subject_type = 'dispensation')"
        ." OR (action IN ('transfer.approved', 'transfer.voided', 'transfer.discrepancy_resolved') AND subject_type = 'transfer')";

    public function up(): void
    {
        $this->replace(
            actions: 'action IN ('.implode(', ', [
                self::PRIOR_ACTIONS,
                "'user.created'",
                "'stock.adjusted'",
            ]).')',
            subjects: 'subject_type IN ('.implode(', ', [
                self::PRIOR_SUBJECTS,
                "'user'",
                "'kardex_movement'",
            ]).')',
            pairs: implode(' OR ', [
                self::PRIOR_PAIRS,
                "(action = 'user.created' AND subject_type = 'user')",
                "(action = 'stock.adjusted' AND subject_type = 'kardex_movement')",
            ]),
            validate: true,
        );
    }

    public function down(): void
    {
        $this->replace(
            actions: 'action IN ('.self::PRIOR_ACTIONS.')',
            subjects: 'subject_type IN ('.self::PRIOR_SUBJECTS.')',
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
