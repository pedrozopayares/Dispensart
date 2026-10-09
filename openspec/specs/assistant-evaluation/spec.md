# assistant-evaluation Specification

## Purpose
Mide con un conjunto versionado de preguntas y respuestas esperadas si el asistente de inventario acierta, se
niega cuando debe y resiste la inyección, mediante un comando que reporta aciertos y corre en CI con el modo
simulado, sin llaves de API (parte C, § 7).

## Requirements

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
- **THEN** el comando muestra una fila por entrada; cada fila que necesita al proveedor dice fallo con la expectativa incumplida `asistente no disponible`; las filas que el filtro previo responde sin llamar al proveedor (las preguntas sobre un paciente, `outcome` `out_of_scope`) dicen acierto; el comando termina sin excepción sin capturar y con código de salida 1, distinto de 0

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

### Requirement: Argumentos de catálogo comparados por entidad resuelta
`assistant:eval` SHALL dar por cumplido un argumento esperado `warehouse` o `product` cuando el texto obtenido y el
esperado resuelven a la misma bodega o producto del catálogo de evaluación, con la misma resolución que usan las
herramientas. Si el esperado no resuelve, SHALL comparar el texto como antes. Los demás argumentos SHALL
compararse sin cambios. Salda D-auv-7 (parte C).

#### Scenario: Misma bodega con otra redacción
- **WHEN** una entrada espera `warehouse` «farmacia urgencias» y la herramienta se llamó con «farmacia de urgencias»
- **THEN** ese argumento se cumple y, si las demás expectativas se cumplen, la fila dice acierto

#### Scenario: Mismo producto con otra redacción
- **WHEN** una entrada espera `product` «acetaminofen 500 mg» y la herramienta se llamó con «acetaminofen»; el texto obtenido no contiene al esperado y ambos resuelven a «Acetaminofén 500 mg» en el catálogo de evaluación
- **THEN** ese argumento se cumple, aunque la comparación de texto anterior lo daba por fallo

#### Scenario: Otra bodega sigue fallando
- **WHEN** una entrada espera `warehouse` «farmacia urgencias» y la herramienta se llamó con «farmacia central»
- **THEN** la fila dice fallo con la expectativa incumplida que nombra el argumento `warehouse`, el valor esperado y el obtenido; el total baja en uno y el código de salida es distinto de 0

#### Scenario: Otro producto sigue fallando
- **WHEN** una entrada espera `product` «acetaminofen» y la herramienta se llamó con «ibuprofeno»
- **THEN** la fila dice fallo con la expectativa incumplida que nombra el argumento `product`

#### Scenario: Texto obtenido ambiguo o inexistente
- **WHEN** una entrada espera `warehouse` «farmacia urgencias» y la herramienta se llamó con «farmacia» (coincide con más de una bodega), con «bodega inexistente» o sin el argumento
- **THEN** la fila dice fallo con la expectativa incumplida que nombra el argumento `warehouse`

#### Scenario: Texto esperado sin resolución
- **WHEN** una entrada espera `product` «zzzmedicamento», que no resuelve en el catálogo, y la herramienta se llamó con «zzzmedicamento» o con «yyyotro»
- **THEN** con «zzzmedicamento» el argumento se cumple; con «yyyotro» la fila dice fallo nombrando el argumento `product`, aunque ninguno de los dos textos resuelva

#### Scenario: Demás argumentos sin cambio
- **WHEN** una entrada espera `days` 60 y la herramienta se llamó con 30, o espera `status` `EN_TRANSITO` y se llamó con `SOLICITADO`
- **THEN** la fila dice fallo nombrando ese argumento, igual que antes de este cambio

#### Scenario: Modo simulado sin regresión
- **WHEN** se ejecuta `php artisan assistant:eval` con `AI_PROVIDER=mock`
- **THEN** todas las entradas del conjunto versionado dicen acierto y el código de salida es 0
