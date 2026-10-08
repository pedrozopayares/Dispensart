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
