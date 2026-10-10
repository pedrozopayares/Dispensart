# M2: agrega una petición a GET /api/no-existe (la guarda debe nombrarla como fuera del contrato).
(.item[] | select(.name == "00 Salud") | .item) += [{
  "name": "Mutante: ruta fuera del contrato",
  "request": {"method": "GET", "header": [], "url": {"raw": "{{baseUrl}}/api/no-existe", "host": ["{{baseUrl}}"], "path": ["api", "no-existe"]}}
}]
