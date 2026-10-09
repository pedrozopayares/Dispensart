## ADDED Requirements

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
