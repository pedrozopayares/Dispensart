import js from '@eslint/js'
import { defineConfig, globalIgnores } from 'eslint/config'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import globals from 'globals'
import tseslint from 'typescript-eslint'

export default defineConfig([
  // `src/lib/api-schema.ts` se genera desde el OpenAPI de la API (npm run api:types).
  globalIgnores(['dist', 'coverage', 'src/lib/api-schema.ts']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      ecmaVersion: 2023,
      globals: globals.browser,
    },
    rules: {
      // Sin datos personales en la consola (RN-10): ningún método, ni siquiera warn/error.
      'no-console': 'error',
    },
  },
  {
    // Componentes generados por shadcn/ui: exportan variantes junto al componente.
    files: ['src/components/ui/**/*.tsx'],
    rules: { 'react-refresh/only-export-components': 'off' },
  },
])
