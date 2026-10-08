# Roadmap — prueba técnica FARTMAR

BORRADOR — orden de tajadas propuesto por el Orchestrator, pendiente de aprobación del usuario. Cada tajada
es un cambio OpenSpec. El orden sigue el peso de evaluación (§ 10 de la prueba) y las dependencias.
Presupuesto: 6–8 horas efectivas.

| # | Change id | Alcance | Partes / reglas | Tier |
|---|---|---|---|---|
| S0 | `add-project-skeleton` | Esqueletos de Laravel 13 y SPA React bajo `software/`, compose, health/ready, esqueleto de CI con lint y tests | D, E | B |
| S1 | `add-catalog-and-identity` | Usuarios con 5 roles, autenticación Sanctum SPA, Policies, bodegas, productos, lotes, datos semilla | A, § 6 | A |
| S2 | `add-stock-and-kardex` | Existencias por bodega+producto+lote, kardex solo inserción, restricciones en DB, ajustes | A, RN-01, RN-06 | A |
| S3 | `add-dispensation` | Pacientes, prescripciones, asignación FEFO, bloqueo, idempotencia, coautorización de control especial, bitácora de acceso, enmascarado | A, RN-02..05, RN-09, RN-10 | A |
| S4 | `add-transfers` | Máquina de estados de traslados, despacho/recepción, discrepancias, segregación de funciones | A, RN-07, RN-08 | A |
| S5 | `add-alerts` | Vencimiento ≤ 90 días, stock bajo mínimo por bodega | A, RN-11 | B |
| S6 | `add-operator-screens` | Pantallas Dispensación, Traslados, Inventario, Kardex | B | B |
| S7 | `add-inventory-assistant` | Interfaz de proveedor LLM, mock/Ollama, herramientas de solo lectura, defensa contra inyección, set de evaluación + script | C | A |
| S8 | `add-delivery-pipeline` | CI/CD completo (imágenes, staging, producción con compuerta), documento de despliegue, README, AI_USAGE.md | D, E | B |
