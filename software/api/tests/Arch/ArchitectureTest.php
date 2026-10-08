<?php

// Reglas de arquitectura base: heredadas por cada slice (S1–S8).

arch('código de aplicación sin funciones de depuración ni inseguras')
    ->preset()->php();

arch('sin primitivas criptográficas o de ejecución inseguras')
    ->preset()->security();

arch('los controladores son delgados: sin acceso directo a la base')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\DatabaseManager']);

arch('las piezas transversales de salud, log y middleware son clases finales')
    ->expect(['App\Health', 'App\Logging', 'App\Http\Middleware'])
    ->toBeFinal();
