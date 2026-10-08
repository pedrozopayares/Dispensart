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

// Reglas de arquitectura del asistente (S7, design D2 y D6). Una regla por objetivo: con un arreglo de espacios de
// nombres, `expect([...])->not->toUse()` de Pest pasa aunque haya uso (comprobado con M4); por eso no se agrupan.

const PATIENT_DATA_MODELS = [Patient::class, Prescription::class, PrescriptionItem::class, Dispensation::class, DispensationLine::class, PatientAccessLog::class];

arch('solo el proveedor de servicios conoce el proveedor simulado')
    ->expect(MockLlmProvider::class)
    ->toOnlyBeUsedIn(AssistantServiceProvider::class);

arch('solo el proveedor de servicios conoce el proveedor Ollama')
    ->expect(OllamaLlmProvider::class)
    ->toOnlyBeUsedIn(AssistantServiceProvider::class);

// inventory-assistant «Datos de pacientes fuera del modelo»: «Herramientas sin acceso a tablas de pacientes».
arch('los servicios del asistente no usan modelos de pacientes, prescripciones ni dispensaciones')
    ->expect('App\Services\Assistant')
    ->not->toUse(PATIENT_DATA_MODELS);

arch('el caso de uso del asistente no usa modelos de pacientes, prescripciones ni dispensaciones')
    ->expect('App\Actions\Assistant')
    ->not->toUse(PATIENT_DATA_MODELS);
