# Avisos de terceros

Skills bajo `.claude/skills/` tomadas de otros repositorios, con sus licencias. Los hashes de cada
`SKILL.md` al momento de copiarlas están en `skills-lock.json`. Todo lo demás en este repositorio se cubre
con el `LICENSE` de la raíz (MIT).

| Skill(s) | Origen | Licencia |
|---|---|---|
| caveman, cavecrew, caveman-commit, caveman-compress, caveman-help, caveman-review, caveman-stats | https://github.com/juliusbrussee/caveman | Apache-2.0 — texto completo en `.claude/skills/caveman/LICENSE` |
| frontend-design | https://github.com/anthropics/skills | Apache-2.0 — texto completo en `.claude/skills/frontend-design/LICENSE.txt` |
| tdd, domain-modeling | https://github.com/mattpocock/skills | MIT |
| api-design-principles, postgresql-table-design, github-actions-templates | https://github.com/wshobson/agents | MIT |
| accessibility | https://github.com/addyosmani/web-quality-skills | MIT |
| supabase-postgres-best-practices | https://github.com/supabase/agent-skills | MIT |
| shadcn | https://github.com/shadcn/ui | MIT |
| vercel-react-best-practices, vercel-composition-patterns, web-design-guidelines | https://github.com/vercel-labs/agent-skills | MIT, declarada en el README de ese repositorio (sin archivo LICENSE) |
| openspec-* (6 skills), `.claude/commands/opsx/*` | https://github.com/Fission-AI/OpenSpec (generadas por `openspec init`) | MIT |

Notas
- `vercel-react-best-practices`: las reglas `server-*` apuntan a React Server Components / Next.js y no
  aplican a esta SPA con Vite. Se conservan intactas para no alterar el original.
- Las skills escritas en este repositorio (`laravel-backend`, `security-privacy`, `spec-writing`) y los
  agentes, hooks y comandos bajo `.claude/` se cubren con el `LICENSE` de la raíz.

Texto de la licencia MIT (aplica a cada entrada MIT de la tabla; los titulares del copyright son los dueños
de cada repositorio):

    Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
    associated documentation files (the "Software"), to deal in the Software without restriction,
    including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
    and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so,
    subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all copies or substantial
    portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT
    LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
    IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
    WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
