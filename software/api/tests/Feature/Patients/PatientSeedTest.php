<?php

use App\Enums\PrescriptionStatus;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Support\BusinessCalendar;
use Database\Seeders\PatientSeeder;
use Database\Seeders\PrescriptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// patients "Pacientes semilla sintéticos" y prescriptions "Prescripciones semilla".

it('siembra exactamente 3 pacientes con documento del rango 99990', function () {
    $this->seed();

    expect(Patient::count())->toBe(3)
        ->and(Patient::pluck('document_number')->every(fn (string $d) => str_starts_with($d, '99990')))->toBeTrue();
});

it('repite la siembra sin duplicar pacientes ni prescripciones y sin reiniciar saldos', function () {
    $this->seed();
    $item = PrescriptionItem::query()->orderBy('id')->firstOrFail();
    DB::table('prescription_items')->where('id', $item->id)->update(['dispensed_quantity' => 1]);

    $this->seed();

    expect(Patient::count())->toBe(3)
        ->and(Prescription::count())->toBe(3)
        ->and($item->fresh()?->dispensed_quantity)->toBe(1);
});

it('da a cada paciente semilla una prescripción vigente del médico semilla y solo una con el controlado', function () {
    $this->seed();
    $medico = User::where('email', 'medico@dispensart.test')->sole();
    $today = BusinessCalendar::today();

    foreach (Patient::with('prescriptions.items.product')->get() as $patient) {
        $prescription = $patient->prescriptions->sole();
        expect($prescription->prescriber_id)->toBe($medico->id)
            ->and($prescription->statusOn($today))->toBe(PrescriptionStatus::Active);
    }

    $withControlled = Prescription::whereHas('items.product', fn ($q) => $q->where('is_controlled', true))->count();
    expect($withControlled)->toBe(1);
});

it('no contiene en el código de siembra documentos fuera del rango 99990 ni nombres sin la marca de sintético', function () {
    $source = (string) file_get_contents(database_path('seeders/PatientSeeder.php'));
    preg_match_all('/\'document_number\' => \'(\d+)\'/', $source, $documents);
    preg_match_all('/\'full_name\' => \'([^\']+)\'/', $source, $names);

    // Control positivo: el barrido encuentra los 3 pacientes declarados.
    expect($documents[1])->toHaveCount(count(PatientSeeder::PATIENTS))
        ->and($names[1])->toHaveCount(count(PatientSeeder::PATIENTS));
    foreach ($documents[1] as $document) {
        expect($document)->toStartWith('99990');
    }
    foreach ($names[1] as $name) {
        expect($name)->toMatch('/Sint[ée]tic[oa]/u');
    }
    // Claves numéricas: PHP las convierte en enteros.
    expect(array_map('strval', array_keys(PrescriptionSeeder::PRESCRIPTIONS)))->each->toStartWith('99990');
});
