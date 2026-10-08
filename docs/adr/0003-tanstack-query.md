---
status: accepted
date: 2026-10-07
---

# TanStack Query como capa de estado del servidor en la SPA

Cuatro pantallas operativas necesitan datos del servidor con caché, invalidación tras escrituras (una
dispensación cambia inventario y kardex), estado pendiente para bloquear el doble envío (criterio evaluado
de forma explícita) y manejo uniforme de errores. TanStack Query da las cuatro; `fetch` + `useEffect` a mano
las reimplementaría.

Restricción que debe respetar: una `Idempotency-Key` por intención del usuario, generada al enviar el
formulario la primera vez y reutilizada en cada reintento de esa misma intención (RN-09). Una clave nueva por
intento anularía la idempotencia. Considerado: SWR (mutaciones más débiles), RTK Query (arrastra Redux), sin
librería.
