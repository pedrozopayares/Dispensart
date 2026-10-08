<?php

use App\Models\Dispensation;
use App\Models\DispensationLine;
use App\Models\Patient;
use App\Models\PatientAccessLog;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Providers\AssistantServiceProvider;
use App\Services\Assistant\Llm\MockLlmProvider;
use App\Services\Assistant\Llm\OllamaLlmProvider;

// Reglas de arquitectura del asistente (S7, design D2 y D6).

arch('solo el proveedor de servicios conoce los proveedores concretos del modelo')
    ->expect([MockLlmProvider::class, OllamaLlmProvider::class])
    ->toOnlyBeUsedIn([AssistantServiceProvider::class]);

// inventory-assistant «Datos de pacientes fuera del modelo»: «Herramientas sin acceso a tablas de pacientes».
arch('el asistente no usa modelos de pacientes, prescripciones ni dispensaciones')
    ->expect(['App\Services\Assistant', 'App\Actions\Assistant'])
    ->not->toUse([Patient::class, Prescription::class, PrescriptionItem::class, Dispensation::class, DispensationLine::class, PatientAccessLog::class]);
