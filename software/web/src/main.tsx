import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from '@/App'
import { createAppQueryClient } from '@/app/app-query-client'
import { createAppRouter } from '@/app/routes'
import { strings } from '@/lib/strings'
import './index.css'

const root = document.getElementById('root')
if (!root) {
  throw new Error(strings.app.missingRoot)
}

const router = createAppRouter()

createRoot(root).render(
  <StrictMode>
    <App router={router} client={createAppQueryClient(router)} />
  </StrictMode>,
)
