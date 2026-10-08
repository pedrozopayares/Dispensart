// Formatos de datos para la interfaz. Fechas del kardex siempre en America/Bogota, nunca en la zona
// del navegador (RN-06).
const bogotaDateTime = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'America/Bogota',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
})

// "AAAA-MM-DD HH:mm" en America/Bogota.
export function formatDateTimeBogota(iso: string): string {
  const parts = Object.fromEntries(
    bogotaDateTime.formatToParts(new Date(iso)).map((part) => [part.type, part.value]),
  )
  return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}`
}

// Cantidad con signo explícito: "+5", "-3".
export function formatSignedQuantity(quantity: number): string {
  return quantity > 0 ? `+${quantity}` : String(quantity)
}
