import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { strings } from '@/lib/strings'

// Página shell mínima de S0: nombre del producto y bienvenida. Sin pantallas de negocio todavía.
export default function App() {
  return (
    <div className="flex min-h-svh flex-col bg-background text-foreground">
      <header className="border-b px-6 py-4">
        <h1 className="text-xl font-semibold">{strings.app.name}</h1>
      </header>
      <main className="flex flex-1 items-start justify-center p-6">
        <Card className="w-full max-w-xl">
          <CardHeader>
            <CardTitle>{strings.shell.welcomeTitle}</CardTitle>
            <CardDescription>{strings.shell.welcomeMessage}</CardDescription>
          </CardHeader>
        </Card>
      </main>
    </div>
  )
}
