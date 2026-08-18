import js from '@eslint/js'
import globals from 'globals'
import tseslint from 'typescript-eslint'

// admin/ bilan bir xil — faqat loyihada allaqachon bor paketlar
// ishlatiladi. Tip tekshiruvini `tsc -b` bajaradi.
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
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
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
