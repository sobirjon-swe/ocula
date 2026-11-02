import { URL } from 'node:url'
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { cyrillic } from '../src/lib/i18n/transliterate.ts'
test('all authored locales contain the same translation keys', async () => {
    const locales = await Promise.all(
        ['uz-latn', 'ru', 'en'].map(async (locale) =>
            JSON.parse(
                await readFile(
                    new URL(
                        '../src/lib/i18n/locales/' + locale + '.json',
                        import.meta.url,
                    ),
                    'utf8',
                ),
            ),
        ),
    )
    assert.deepEqual(
        Object.keys(locales[0]).sort(),
        Object.keys(locales[1]).sort(),
    )
    assert.deepEqual(
        Object.keys(locales[0]).sort(),
        Object.keys(locales[2]).sort(),
    )
})
test('Uzbek Cyrillic is generated from canonical Latin copy', () => {
    assert.equal(cyrillic("O'zbekcha ko‘zoynak"), 'Ўзбекча кўзойнак')
    assert.equal(cyrillic('Shifokor'), 'Шифокор')
})
