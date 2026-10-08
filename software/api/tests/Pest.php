<?php

use Tests\TestCase;

/*
| Pest (ADR-0002). Toda prueba de Feature corre contra PostgreSQL (phpunit.xml fuerza pgsql y
| dispensart_test). RefreshDatabase se declara por archivo: las pruebas de base caída no lo usan.
*/

pest()->extend(TestCase::class)->in('Feature');
