# M5: la aserción de la vista previa espera fuera de las asignaciones el lote vigente L-LOS-2403, no el vencido.
(.item[] | select(.name == "02 Dispensación FEFO e idempotencia") | .item[]
  | select(.name == "Auxiliar: vista previa FEFO de 2 unidades de MED-004 en BH")
  | .event[] | select(.listen == "test") | .script.exec) |= map(sub("const EXPIRED_LOT = 'L-LOS-2401'"; "const EXPIRED_LOT = 'L-LOS-2403'"))
