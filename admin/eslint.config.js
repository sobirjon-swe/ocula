import js from '@eslint/js'
import globals from 'globals'
import tseslint from 'typescript-eslint'

// Faqat loyihada allaqachon bor paketlar ishlatiladi (@eslint/js,
// typescript-eslint, globals). Tip tekshiruvini `tsc -b` bajaradi —
// bu yerda type-aware qoidalar yoqilmagan, shuning uchun lint tez ishlaydi.
export default tseslint.config(
  {
    ignores: ['dist', 'node_modules', '*.tsbuildinfo'],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
    },
    rules: {
      // `_` bilan boshlanadigan argument ataylab ishlatilmaydi.
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
      // `import type` majburiy — tsconfig'da `verbatimModuleSyntax` yoqilgan.
      '@typescript-eslint/consistent-type-imports': [
        'error',
        { prefer: 'type-imports', fixStyle: 'inline-type-imports' },
      ],
    },
  },
  {
    files: ['vite.config.ts', 'eslint.config.js'],
    languageOptions: {
      globals: globals.node,
    },
  },
)
