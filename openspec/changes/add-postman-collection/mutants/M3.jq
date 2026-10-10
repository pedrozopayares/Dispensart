# M3: la repetición de la dispensación usa otra Idempotency-Key (deja de ser una repetición idempotente).
(.item[] | select(.name == "02 Dispensación FEFO e idempotencia") | .item[]
  | select(.name == "Auxiliar: repite la dispensación con la misma clave")
  | .request.header[] | select(.key == "Idempotency-Key") | .value) = "postman-otra-clave-{{runId}}"
