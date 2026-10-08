<?php

namespace App\Enums;

/**
 * Tipos de documento de identidad del paciente (patients). La base repite el conjunto en
 * patients_document_type_check.
 */
enum DocumentType: string
{
    case CedulaCiudadania = 'CC';
    case TarjetaIdentidad = 'TI';
    case CedulaExtranjeria = 'CE';
    case Pasaporte = 'PA';
    case RegistroCivil = 'RC';
}
