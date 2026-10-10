# M4: la petición del médico en la carpeta 08 deja de excluir X-XSRF-TOKEN (el token viaja: 403 en vez de 419).
(.item[] | select(.name == "08 Permisos denegados") | .item[]
  | select(.name == "Médico: crea bodega sin X-XSRF-TOKEN (CSRF antes que permisos)")
  | .request.header) |= map(select(.key != "X-Omit-Headers"))
