import { useQueryClient } from '@tanstack/react-query'
import { ShieldAlertIcon } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { EmptyState } from '@/components/empty-state'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { DispensationForm, type DispensationFormProps } from '@/features/dispensations/dispensation-form'
import { usePatient } from '@/features/dispensations/queries'
import type { PatientRecord as Patient, Prescription, PrescriptionStatus } from '@/lib/api-types'
import { queryKeys } from '@/lib/query-keys'
import { format, strings } from '@/lib/strings'

type PatientRecordProps = {
  patientId: number
  // Sin `dispensations.create` la ficha queda en modo consulta (auditor, médico).
  canDispense: boolean
} & Pick<DispensationFormProps, 'intent' | 'onCompleted'>

// Ficha del paciente: datos tal como los entrega la API (enmascarados para el auditor, RN-10) y sus
// prescripciones con estado y saldos. Solo una prescripción Vigente con pendiente admite dispensar.
export function PatientRecord({ patientId, canDispense, intent, onCompleted }: PatientRecordProps) {
  const client = useQueryClient()
  const patient = usePatient(patientId)
  const [dispensingId, setDispensingId] = useState<number | null>(null)
  // Rechazo de la API porque la prescripción cambió (vencida, agotada, excedida).
  const [notice, setNotice] = useState<unknown>(null)

  if (patient.isPending) return <LoadingState label={strings.dispensation.patient.loading} />
  if (patient.isError) {
    return (
      <ErrorMessage
        error={patient.error}
        retrying={patient.isFetching}
        onRetry={() => void patient.refetch()}
      />
    )
  }

  const prescriptions = patient.data.prescriptions ?? []

  const prescriptionChanged = (error: unknown) => {
    setNotice(error)
    void client.invalidateQueries({ queryKey: queryKeys.patient(patientId) })
  }

  return (
    <section className="flex flex-col gap-4" aria-label={strings.dispensation.patient.title}>
      <PatientCard patient={patient.data} />
      {notice !== null && <ErrorMessage error={notice} />}
      <h3 className="text-lg font-semibold">{strings.dispensation.patient.prescriptions}</h3>
      {prescriptions.length === 0 ? (
        <EmptyState message={strings.dispensation.patient.noPrescriptions} />
      ) : (
        prescriptions.map((prescription) => {
          const eligible = canDispense && isDispensable(prescription)
          return (
            <PrescriptionCard
              key={prescription.id}
              prescription={prescription}
              onDispense={
                eligible && dispensingId !== prescription.id
                  ? () => {
                      setNotice(null)
                      setDispensingId(prescription.id)
                    }
                  : undefined
              }
            >
              {eligible && dispensingId === prescription.id && (
                <DispensationForm
                  prescription={prescription}
                  intent={intent}
                  onCompleted={onCompleted}
                  onPrescriptionChanged={prescriptionChanged}
                  onCancel={() => setDispensingId(null)}
                />
              )}
            </PrescriptionCard>
          )
        })
      )}
    </section>
  )
}

const isDispensable = (prescription: Prescription) =>
  prescription.status === 'vigente' && prescription.items.some((item) => item.pending_quantity > 0)

function statusLabel(status: string): string {
  const labels: Record<PrescriptionStatus, string> = strings.dispensation.prescription.status
  return Object.hasOwn(labels, status)
    ? labels[status as PrescriptionStatus]
    : strings.dispensation.prescription.unknownStatus
}

function PatientCard({ patient }: { patient: Patient }) {
  const labels = strings.dispensation.patient
  return (
    <Card>
      <CardHeader>
        <CardTitle>{patient.full_name}</CardTitle>
        <CardDescription>
          {labels.document}: {patient.document_type} {patient.document_number}
        </CardDescription>
        {patient.masked && (
          <CardAction>
            <Badge variant="secondary">
              <ShieldAlertIcon aria-hidden="true" />
              {labels.masked}
            </Badge>
          </CardAction>
        )}
      </CardHeader>
      {(patient.birth_date !== null || patient.phone !== null) && (
        <CardContent className="flex flex-wrap gap-6 text-sm">
          {patient.birth_date !== null && (
            <p>
              {labels.birthDate}: {patient.birth_date}
            </p>
          )}
          {patient.phone !== null && (
            <p>
              {labels.phone}: {patient.phone}
            </p>
          )}
        </CardContent>
      )}
    </Card>
  )
}

function PrescriptionCard({
  prescription,
  onDispense,
  children,
}: {
  prescription: Prescription
  onDispense?: () => void
  children?: ReactNode
}) {
  const labels = strings.dispensation.prescription
  const active = prescription.status === 'vigente'
  return (
    <Card aria-label={format(labels.title, { id: String(prescription.id) })} role="region">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          {format(labels.title, { id: String(prescription.id) })}
          <Badge variant={active ? 'default' : 'outline'}>{statusLabel(prescription.status)}</Badge>
        </CardTitle>
        <CardDescription>
          {format(labels.validUntil, { date: prescription.valid_until })}
          {' · '}
          {format(labels.prescriber, { name: prescription.prescriber.name })}
        </CardDescription>
        {onDispense !== undefined && (
          <CardAction>
            <Button onClick={onDispense}>{labels.dispense}</Button>
          </CardAction>
        )}
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <Table>
          <TableCaption className="sr-only">{labels.caption}</TableCaption>
          <TableHeader>
            <TableRow>
              <TableHead>{labels.columns.product}</TableHead>
              <TableHead className="text-right">{labels.columns.prescribed}</TableHead>
              <TableHead className="text-right">{labels.columns.dispensed}</TableHead>
              <TableHead className="text-right">{labels.columns.pending}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {prescription.items.map((item) => (
              <TableRow key={item.id}>
                <TableCell>
                  <span className="flex flex-wrap items-center gap-2">
                    {item.product.name}
                    {item.product.is_controlled && <Badge variant="outline">{labels.controlled}</Badge>}
                  </span>
                </TableCell>
                <TableCell className="text-right">{item.prescribed_quantity}</TableCell>
                <TableCell className="text-right">{item.dispensed_quantity}</TableCell>
                <TableCell className="text-right">{item.pending_quantity}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        {children}
      </CardContent>
    </Card>
  )
}
