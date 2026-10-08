import { useId } from 'react'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import {
  argumentLines,
  callStatusLabel,
  outcomeLabel,
  toolLabel,
} from '@/features/assistant/assistant-labels'
import type { AssistantAnswer } from '@/lib/api-types'
import { strings } from '@/lib/strings'

const labels = strings.assistant

export type AssistantEntry = { id: number; question: string; answer: AssistantAnswer }

// Una pregunta del historial: etiqueta por `outcome` (estilo propio para `answered`), `answer` como
// texto plano línea por línea (React escapa: nada se interpreta como HTML) y las consultas hechas.
export function AssistantAnswerCard({ entry }: { entry: AssistantEntry }) {
  const questionId = useId()
  const { outcome, answer, tool_calls: toolCalls } = entry.answer
  return (
    <Card className="gap-4 py-4">
      <article aria-labelledby={questionId} className="flex flex-col gap-4">
        <CardHeader className="flex flex-wrap items-start justify-between gap-2 px-4">
          <h3 id={questionId} className="font-medium break-words whitespace-pre-line">
            {entry.question}
          </h3>
          <Badge variant={outcome === 'answered' ? 'default' : 'outline'}>{outcomeLabel(outcome)}</Badge>
        </CardHeader>
        <CardContent className="flex flex-col gap-4 px-4">
          <div className="flex flex-col text-sm">
            {answer.split('\n').map((line, index) => (
              <p key={index} className="min-h-5 break-words">
                {line}
              </p>
            ))}
          </div>
          <section aria-label={labels.toolCalls} className="flex flex-col gap-2">
            <h4 className="text-xs font-medium text-muted-foreground">{labels.toolCalls}</h4>
            {toolCalls.length === 0 ? (
              <p className="text-sm text-muted-foreground">{labels.noToolCalls}</p>
            ) : (
              <ul className="flex flex-col gap-2">
                {toolCalls.map((call, index) => (
                  <li key={index} className="flex flex-col gap-1 rounded-md border px-3 py-2 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">{toolLabel(call.tool)}</span>
                      <Badge variant="secondary">{callStatusLabel(call.status)}</Badge>
                    </div>
                    {Object.keys(call.arguments).length > 0 && (
                      <ul className="flex flex-col text-muted-foreground">
                        {argumentLines(call.arguments).map((line) => (
                          <li key={line}>{line}</li>
                        ))}
                      </ul>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </section>
        </CardContent>
      </article>
    </Card>
  )
}
