// Módulo central de textos de la interfaz. Todo texto visible sale de aquí, en español (ADR-0006).
// Las plantillas usan marcadores `{nombre}` y se completan con `format()`.
export const strings = {
  app: {
    name: 'Dispensart',
  },
  shell: {
    welcomeTitle: 'Bienvenido',
    welcomeMessage:
      'Dispensación de medicamentos, inventario por lote y traslados entre bodegas.',
    logout: 'Cerrar sesión',
    loggingOut: 'Cerrando sesión…',
    logoutFailed: 'No pudimos cerrar la sesión. Intenta de nuevo.',
  },
  home: {
    greeting: 'Bienvenido, {name}',
    emptyTitle: 'Sin pantallas de operación',
    emptyMessage: 'Las pantallas de operación aparecerán aquí.',
  },
  session: {
    loading: 'Cargando sesión…',
    loadFailed: 'No pudimos verificar tu sesión.',
    retry: 'Reintentar',
    expired: 'Tu sesión expiró. Inicia sesión de nuevo.',
  },
  login: {
    title: 'Iniciar sesión',
    email: 'Correo electrónico',
    password: 'Contraseña',
    submit: 'Iniciar sesión',
    submitting: 'Ingresando…',
    required: 'Este campo es obligatorio.',
  },
  // Etiquetas de rol: el código del rol nunca se muestra al usuario.
  roles: {
    auxiliar_farmacia: 'Auxiliar de farmacia',
    regente_farmacia: 'Regente de farmacia',
    medico: 'Médico',
    auditor: 'Auditor',
    admin: 'Administrador',
    unknown: 'Rol sin etiqueta',
  },
  // Mensajes por `code` de la API (forma de rechazo única, design D5).
  errors: {
    invalidCredentials: 'Correo o contraseña incorrectos.',
    tooManyAttempts: 'Demasiados intentos. Espera un momento antes de volver a intentar.',
    csrfExpired: 'La sesión de seguridad expiró. Recarga la página e intenta de nuevo.',
    network: 'No pudimos conectar con el servidor. Intenta de nuevo.',
    forbidden: 'No tienes permiso para realizar esta acción.',
    notFound: 'El recurso solicitado no existe.',
    validation: 'Revisa los datos ingresados.',
    unexpected: 'Ocurrió un error inesperado. Intenta de nuevo.',
    // Una línea por faltante (`shortages`); sin detalle, el texto general.
    insufficientStock: 'Stock insuficiente: {product} necesita {requested} y hay {available} disponibles.',
    insufficientStockGeneric: 'Stock insuficiente para completar la operación.',
    unknownProduct: 'un producto',
    lotExpired: 'El lote está vencido y no puede usarse.',
    prescriptionExpired: 'La prescripción está vencida y no se puede dispensar.',
    prescriptionExhausted: 'La prescripción ya fue dispensada por completo.',
    exceedsPrescription: 'La cantidad supera lo pendiente en la prescripción.',
    invalidAuthorizer:
      'Las credenciales del autorizador no son válidas o no tiene permiso para autorizar.',
    authorizerMustDiffer: 'El autorizador debe ser un regente distinto de quien dispensa.',
    authorizationRequired: 'Este medicamento requiere la autorización de un regente.',
    idempotencyKeyReused:
      'Esta confirmación ya se usó con otros datos. Revisa la asignación y confirma de nuevo.',
    segregationOfDuties: 'Quien solicitó el traslado no puede aprobarlo.',
    invalidTransferTransition:
      'El traslado cambió de estado mientras lo revisabas. Actualizamos la información.',
  },
  common: {
    retry: 'Reintentar',
    previous: 'Anterior',
    next: 'Siguiente',
    page: 'Página {page} de {total}',
    working: 'Procesando…',
    cancel: 'Cancelar',
    required: 'Este campo es obligatorio.',
  },
  // Menú de pantallas de operación (operator-workspace "Navegación por rol").
  nav: {
    label: 'Pantallas de operación',
    dispensations: 'Dispensación',
    transfers: 'Traslados',
    inventory: 'Inventario',
    kardex: 'Kardex',
  },
  guard: {
    forbidden: 'No tienes permiso para ver esta pantalla.',
    backHome: 'Volver al inicio',
  },
  filters: {
    warehouse: 'Bodega',
    allWarehouses: 'Todas las bodegas',
    product: 'Producto',
    allProducts: 'Todos los productos',
    lot: 'Lote',
    allLots: 'Todos los lotes',
    lotNeedsProduct: 'Elige un producto primero',
  },
  inventory: {
    title: 'Inventario',
    description: 'Existencias por bodega, producto y lote con su vencimiento.',
    loading: 'Cargando inventario…',
    empty: 'No hay existencias para los filtros elegidos.',
    caption: 'Existencias por bodega y lote',
    columns: {
      warehouse: 'Bodega',
      product: 'Producto',
      lot: 'Lote',
      expiresOn: 'Vencimiento',
      quantity: 'Cantidad',
    },
    controlled: 'Control especial',
    expired: 'Vencido',
  },
  kardex: {
    title: 'Kardex',
    description: 'Movimientos de inventario, del más reciente al más antiguo.',
    loading: 'Cargando movimientos…',
    empty: 'No hay movimientos para los filtros elegidos.',
    caption: 'Movimientos del kardex',
    pagination: 'Paginación del kardex',
    system: 'Sistema',
    columns: {
      date: 'Fecha',
      type: 'Tipo',
      warehouse: 'Bodega',
      product: 'Producto',
      lot: 'Lote',
      quantity: 'Cantidad',
      balance: 'Saldo',
      user: 'Usuario',
      reason: 'Motivo',
    },
    // Tipos de movimiento (RN-06): nunca el literal de la API.
    types: {
      entrada: 'Entrada',
      salida_dispensacion: 'Salida por dispensación',
      salida_traslado: 'Salida por traslado',
      entrada_traslado: 'Entrada por traslado',
      ajuste: 'Ajuste',
    },
    unknownType: 'Movimiento',
  },
  // Pantalla Dispensación (dispensation-screen).
  dispensation: {
    title: 'Dispensación',
    description: 'Busca al paciente, revisa los lotes que asigna FEFO y confirma la entrega.',
    search: {
      label: 'Documento o nombre del paciente',
      submit: 'Buscar',
      hint: 'Enter busca; las flechas recorren los resultados y Enter abre la ficha.',
      tooShort: 'Escribe al menos 3 caracteres.',
      loading: 'Buscando pacientes…',
      empty: 'No encontramos pacientes con ese documento o nombre.',
      results: 'Pacientes encontrados',
    },
    patient: {
      loading: 'Cargando ficha del paciente…',
      title: 'Ficha del paciente',
      document: 'Documento',
      name: 'Nombre',
      birthDate: 'Fecha de nacimiento',
      phone: 'Teléfono',
      masked: 'Datos enmascarados',
      prescriptions: 'Prescripciones',
      noPrescriptions: 'Este paciente no tiene prescripciones.',
    },
    prescription: {
      title: 'Prescripción #{id}',
      validUntil: 'Vigencia: {date}',
      prescriber: 'Prescriptor: {name}',
      // Estados calculados por la API (RN-04): nunca el literal crudo.
      status: {
        vigente: 'Vigente',
        vencida: 'Vencida',
        agotada: 'Agotada',
      },
      unknownStatus: 'Sin estado',
      caption: 'Ítems de la prescripción',
      columns: {
        product: 'Producto',
        prescribed: 'Prescrita',
        dispensed: 'Dispensada',
        pending: 'Pendiente',
      },
      controlled: 'Control especial',
      dispense: 'Dispensar esta prescripción',
    },
    form: {
      title: 'Dispensar prescripción #{id}',
      warehouse: 'Bodega',
      chooseWarehouse: 'Elige una bodega',
      quantity: 'Cantidad de {product}',
      pending: 'Pendiente: {pending}',
      warehouseRequired: 'Elige una bodega.',
      quantitiesRequired: 'Indica al menos una cantidad.',
      quantityTooHigh: 'La cantidad no puede superar lo pendiente ({pending}).',
      quantityInvalid: 'Escribe un número entero entre 0 y {pending}.',
      preview: 'Ver lotes asignados',
      previewing: 'Calculando asignación…',
      previewNeeded: 'Pulsa "Ver lotes asignados" para revisar la asignación antes de confirmar.',
      cancel: 'Cancelar',
    },
    preview: {
      title: 'Lotes asignados (FEFO)',
      caption: 'Lotes asignados a {product}',
      columns: {
        lot: 'Lote',
        expiresOn: 'Vencimiento',
        quantity: 'Cantidad',
      },
      expiredExcluded: '{count} unidades en lotes vencidos no se usan.',
    },
    authorizer: {
      notice:
        'Medicamento de control especial: requiere autorización de un regente distinto de quien dispensa.',
      email: 'Correo del regente que autoriza',
      password: 'Contraseña del regente',
    },
    confirm: 'Confirmar dispensación',
    confirming: 'Confirmando…',
    recalculate: 'Recalcular asignación',
    success: {
      title: 'Dispensación registrada',
      caption: 'Líneas de la dispensación',
      columns: {
        product: 'Producto',
        lot: 'Lote',
        expiresOn: 'Vencimiento',
        quantity: 'Cantidad',
      },
      newDispensation: 'Nueva dispensación',
    },
  },
  a11y: {
    loading: 'Cargando',
  },
} as const

export type RoleCode = Exclude<keyof typeof strings.roles, 'unknown'>

// Completa una plantilla del módulo con valores de datos (nombre del usuario, etc.).
export function format(template: string, values: Record<string, string>): string {
  return template.replace(/\{(\w+)\}/g, (match, key: string) => values[key] ?? match)
}

// Etiqueta en español de un rol; un código desconocido nunca se muestra crudo.
export function roleLabel(role: string): string {
  return Object.hasOwn(strings.roles, role) && role !== 'unknown'
    ? strings.roles[role as RoleCode]
    : strings.roles.unknown
}
