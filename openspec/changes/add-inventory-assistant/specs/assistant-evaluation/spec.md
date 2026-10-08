# Spec Delta — assistant-evaluation

## Purpose

Mide con un conjunto versionado de preguntas y respuestas esperadas si el asistente de inventario acierta, se
niega cuando debe y resiste la inyección, mediante un comando que reporta aciertos y corre en CI con el modo
simulado, sin llaves de API (parte C, § 7).

## ADDED Requirements

### Requirement: Conjunto de evaluación versionado
El repositorio SHALL incluir un conjunto de al menos 10 preguntas en español, cada una con el rol que pregunta
y su respuesta esperada: `outcome`, herramienta y argumentos esperados, fragmentos que `answer` debe contener y
fragmentos que no debe contener. SHALL cubrir las 4 herramientas, filtro por bodega y por plazo, fuera de
alcance, pedido de escritura, pregunta sobre un paciente, rol sin permiso y observación maliciosa (parte C).

#### Scenario: Cobertura mínima
- **WHEN** se lee el conjunto de evaluación versionado
- **THEN** tiene al menos 10 entradas, cada herramienta aparece como esperada en al menos una, y hay al menos una entrada por cada categoría: filtro por bodega, plazo explícito, fuera de alcance, pedido de escritura, pregunta sobre un paciente, rol `medico` con `not_permitted` y traslado con observación maliciosa

#### Scenario: Entrada incompleta
- **WHEN** una entrada del conjunto no declara `outcome` esperado, o declara una herramienta fuera del catálogo
- **THEN** el comando termina con código de salida distinto de 0, nombra la entrada defectuosa y no imprime un total de aciertos

### Requirement: Comando que reporta aciertos
`php artisan assistant:eval` SHALL responder cada pregunta del conjunto por el mismo servicio que usa la ruta, con
el proveedor configurado y el rol de la entrada, y mostrar una fila por pregunta (acierto o fallo y la primera
expectativa incumplida) y el total `aciertos/total`. Una entrada acierta solo si cumple todas sus expectativas.
SHALL terminar con código 0 solo si todas aciertan (parte C, § 7).

#### Scenario: Todas aciertan con el modo simulado
- **WHEN** se ejecuta `php artisan assistant:eval` con `AI_PROVIDER=mock`
- **THEN** cada fila dice acierto, el total es igual al número de entradas y el código de salida es 0

#### Scenario: Una respuesta equivocada cuenta como fallo
- **WHEN** se altera una regla del modo simulado para que una pregunta use otra herramienta, o se cambia un fragmento esperado de una entrada por uno que la respuesta no contiene
- **THEN** esa fila dice fallo con la expectativa incumplida, el total baja en uno y el código de salida es distinto de 0

#### Scenario: Archivo ausente o mal formado
- **WHEN** el conjunto de evaluación no existe o no es JSON válido
- **THEN** el comando termina con código de salida distinto de 0 y un mensaje en español, sin traza y sin informar `0/0` como éxito

#### Scenario: Proveedor no disponible
- **WHEN** se ejecuta con `AI_PROVIDER=ollama` y el servidor Ollama no responde
- **THEN** cada fila dice fallo por asistente no disponible, el comando termina sin excepción sin capturar y con código de salida distinto de 0

### Requirement: Evaluación aislada de los datos operativos
El comando SHALL evaluar sobre datos de evaluación propios y conocidos, de modo que su resultado no dependa de
los cambios hechos a los datos operativos, y SHALL NOT dejar cambios persistentes en la base.

#### Scenario: Sin cambios persistentes
- **WHEN** se cuentan las filas de existencias, movimientos de kardex, traslados, pacientes y bitácoras antes y después de ejecutar el comando
- **THEN** los conteos son iguales

#### Scenario: Datos operativos alterados
- **WHEN** antes de ejecutar el comando se ajusta una existencia semilla y se crea un traslado en los datos operativos
- **THEN** el comando con `AI_PROVIDER=mock` sigue reportando todas las entradas como acierto

#### Scenario: Base de evaluación no creable
- **WHEN** el usuario de la base no puede crear la base de evaluación desechable, o el nombre de esa base coincide con el de la base operativa
- **THEN** el comando termina con código de salida 2 y un mensaje en español, sin evaluar ni informar total de aciertos, y los conteos de filas de la base operativa son iguales antes y después

### Requirement: Evaluación en CI con el modo simulado
El workflow de CI SHALL ejecutar `assistant:eval` con `AI_PROVIDER=mock` en el trabajo de backend, sin secretos
ni red externa, y SHALL fallar si el comando termina con código distinto de 0.

#### Scenario: Asistente correcto
- **WHEN** se hace push a `dev` de un commit bajo `software/` con el asistente correcto
- **THEN** el paso de evaluación del asistente termina en éxito y su salida muestra el total de aciertos

#### Scenario: Regresión del asistente
- **WHEN** un commit rompe una regla del modo simulado de modo que una entrada falla
- **THEN** el paso de evaluación termina en fallo y el workflow queda en fallo

#### Scenario: Sin secretos ni llaves
- **WHEN** se lee el paso de evaluación en `.github/workflows/ci.yml`
- **THEN** no referencia `secrets.` ni llave de proveedor alguno y fija `AI_PROVIDER` en `mock`
