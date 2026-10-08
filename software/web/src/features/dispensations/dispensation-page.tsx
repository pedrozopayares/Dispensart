import { useState } from 'react'
import { useOutletContext } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { CompletedDispensation } from '@/features/dispensations/dispensation-form'
import { PatientRecord } from '@/features/dispensations/patient-record'
import { PatientSearch } from '@/features/dispensations/patient-search'
import { can } from '@/lib/abilities'
import type { AuthenticatedUser } from '@/lib/api'
import type { PreviewRequest } from '@/lib/api-types'
import { strings } from '@/lib/strings'
import { useIdempotentIntent } from '@/lib/use-idempotent-intent'

// Pantalla Dispensación (/dispensations): buscar paciente → ficha → vista previa FEFO → confirmar.
// Sin `dispensations.create` (auditor, médico) queda en modo consulta. Nada del paciente va a la URL.
export function DispensationPage() {
  const user = useOutletContext<AuthenticatedUser>()
  const canDispense = can(user, 'dispensations.create')
  // Una clave por intención para toda la pantalla (design D3): sobrevive a cerrar el formulario.
  const intent = useIdempotentIntent<PreviewRequest>()
  const [patientId, setPatientId] = useState<number | null>(null)
  const [completed, setCompleted] = useState<CompletedDispensation | null>(null)
  // Cambia con "Nueva dispensación": la búsqueda se monta de nuevo, vacía y con el foco.
  const [round, setRound] = useState(0)

  const startOver = () => {
    setCompleted(null)
    setPatientId(null)
    setRound((value) => value + 1)
  }

  return (
    <section className="flex w-full max-w-5xl flex-col gap-6">
      <PageHeader title={strings.dispensation.title} description={strings.dispensation.description} />
      <PatientSearch
        key={round}
        onSelect={(id) => {
          setCompleted(null)
          setPatientId(id)
        }}
      />
      {completed !== null ? (
        <DispensationSummary completed={completed} onNew={startOver} />
      ) : (
        patientId !== null && (
          <PatientRecord
            key={patientId}
            patientId={patientId}
            canDispense={canDispense}
            intent={intent}
            onCompleted={setCompleted}
          />
        )
      )}
    </section>
  )
}

// Resumen tras confirmar (también cuando la API repite la respuesta original): lote, vencimiento y
// cantidad de cada línea.
function DispensationSummary({
  completed: { dispensation, prescription },
  onNew,
}: {
  completed: CompletedDispensation
  onNew: () => void
}) {
  const labels = strings.dispensation.success
  const productName = (productId: number) =>
    prescription.items.find((item) => item.product.id === productId)?.product.name ??
    strings.errors.unknownProduct
  return (
    <Card role="status" aria-live="polite">
      <CardHeader>
        <CardTitle>{labels.title}</CardTitle>
      </CardHeader>
      <CardContent>
        <Table>
          <TableCaption className="sr-only">{labels.caption}</TableCaption>
          <TableHeader>
            <TableRow>
              <TableHead>{labels.columns.product}</TableHead>
              <TableHead>{labels.columns.lot}</TableHead>
              <TableHead>{labels.columns.expiresOn}</TableHead>
              <TableHead className="text-right">{labels.columns.quantity}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {dispensation.lines.map((line) => (
              <TableRow key={line.id}>
                <TableCell>{productName(line.product_id)}</TableCell>
                <TableCell className="font-mono">{line.lot_code}</TableCell>
                <TableCell>{line.expires_on}</TableCell>
                <TableCell className="text-right">{line.quantity}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
      <CardFooter>
        <Button onClick={onNew}>{labels.newDispensation}</Button>
      </CardFooter>
    </Card>
  )
}
