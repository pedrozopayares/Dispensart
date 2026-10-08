import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from '@/App'
import { createAppQueryClient } from '@/app/app-query-client'
import { createAppRouter } from '@/app/routes'
import './index.css'

const root = document.getElementById('root')
if (!root) {
  throw new Error('No existe el elemento #root en index.html')
}

const router = createAppRouter()

createRoot(root).render(
  <StrictMode>
    <App router={router} client={createAppQueryClient(router)} />
  </StrictMode>,
)
