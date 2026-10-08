---
name: "Nitro"
description: "Modo directo: ejecuta tareas puntuales tú mismo, sin ciclo completo del harness"
category: "Workflow"
tags: ["workflow", "fast", "direct"]
---

Las tareas que te voy a dar durante esta sesión, las debes ejecutar tú mismo inmediatamente, sin pasar por todo el ciclo harness. Vas a cargar todas las skills necesarias del harness y a asumir los roles agenticos pertinentes para completar tú mismo el trabajo según el caso. El objetivo es exprimir al máximo la guía del harness sin ralentizar el trabajo ni aumentar los costos del proceso. Serán tareas puntuales. Velocidad, sin sacrificar eficacia, consistencia, trazabilidad, seguridad. Como Vin Diesel en la película Fast And Furious, dominas el automóvil, no al reves.

Lo único que vas a responder a este comando será "VIVO MI VIDA UN CUARTO DE MILLA A LA VEZ" o "BANZA!!".

---

## Lo que `/nitro` NO te concede — leer antes de arrancar

Heredado del harness anterior del autor, donde el modo gastó ciclos en redacción mientras el producto no
se movía. `/nitro` te deja **asumir los roles**; no te da autoridad nueva.

**La velocidad viene de no volver a derivar lo ya decidido. Nunca de volver a decidirlo.**

1. **Redacción no es defecto.** Un hallazgo que no cambia código, ni el resultado de una prueba, ni una
   decisión, **no reabre un gate**. Se anota y se cierra. (Iron rule 12, `CLAUDE.md`.)
2. **Prosa jamás vuelve al `final-auditor`.** `verification.md`, `journal.md`, `tasks.md`, `proposal.md`,
   `design.md`, `DEBT.md` → `spec-validator` o nadie.
3. **El registro se escribe UNA vez, al cierre.** No se itera ronda tras ronda.
4. **Tope duro: 3 corridas de suite completa por cambio.** La cuarta es PARAR y preguntar al usuario.
   Re-correr la suite para re-derivar una cifra de un documento no es una de las tres.
5. **Triaje por peso de evaluación antes de abrir tajada.** ¿Hay camino de esta fila a un criterio calificado
   de la prueba (§ 10) o a una falla que vea un regente o un auxiliar? Si no, va a lote, no a ciclo propio.
6. **`/nitro` no levanta ningún gate.** GATE 1 sigue siendo del usuario, GATE 2 sigue siendo del
   `final-auditor` (Iron rules 1 y 4). Ejecutar los roles tú mismo no es emitir sus veredictos.
7. **`/nitro` no ensancha el alcance que el usuario aprobó.** Si al trabajar aparece algo contiguo, se
   FILA como deuda y se nombra; no se barre dentro.
8. **`/nitro` no reabre una decisión congelada.** ADRs, la tabla de tiers, el presupuesto de suite, los
   controladores delgados, el módulo de strings, la política de costo cero y la ley de Git ya están
   decididos. Se respetan como están escritos.
9. **La ley del repo gana sobre esta skill** (Iron rule 10). Si algo aquí contradice `CLAUDE.md`, manda
   `CLAUDE.md`.

**Señal de que te saliste:** la tajada es mayormente registro. Si `verification.md` § 0 no puede mostrar
líneas de producto y de prueba frente a líneas de registro sin que dé vergüenza, la tajada debió ser más
chica o ir en lote.
