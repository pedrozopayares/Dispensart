<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Bitácora de operaciones sensibles (S3) ampliada a traslados (design D12): aprobar, anular y resolver una
| discrepancia, sobre el traslado. Reemplaza por nombre los tres CHECK de S3. down() repone los de S3 con NOT
| VALID: audit_events es de solo inserción y las filas transfer.* ya escritas no se pueden borrar.
*/
return new class extends Migration
{
    private const S3_ACTIONS = "'prescription.created', 'dispensation.created', 'controlled_drug.authorized', 'controlled_drug.authorization_failed'";

    private const S3_PAIRS = "(action IN ('prescription.created', 'controlled_drug.authorization_failed') AND subject_type = 'prescription')"
        ." OR (action IN ('dispensation.created', 'controlled_drug.authorized') AND subject_type = 'dispensation')";

    private const TRANSFER_ACTIONS = "'transfer.approved', 'transfer.voided', 'transfer.discrepancy_resolved'";

    public function up(): void
    {
        $this->replace(
            'action IN ('.self::S3_ACTIONS.', '.self::TRANSFER_ACTIONS.')',
            "subject_type IN ('prescription', 'dispensation', 'transfer')",
            self::S3_PAIRS.' OR (action IN ('.self::TRANSFER_ACTIONS.") AND subject_type = 'transfer')",
            validate: true,
        );
    }

    public function down(): void
    {
        $this->replace(
            'action IN ('.self::S3_ACTIONS.')',
            "subject_type IN ('prescription', 'dispensation')",
            self::S3_PAIRS,
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
