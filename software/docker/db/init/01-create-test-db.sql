-- Base de pruebas de Pest (design D3). Corre una sola vez, con el volumen de datos vacío,
-- como POSTGRES_USER, que queda como dueño. Si el volumen ya existía: `docker compose down -v`.
CREATE DATABASE dispensart_test;
