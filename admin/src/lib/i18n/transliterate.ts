// Uzbek Cyrillic is derived from the canonical Uzbek copy, as on the API.
export function cyrillic(text: string): string {
    const pairs: Record<string, string> = {
        "o'": 'ў',
        'o‘': 'ў',
        oʻ: 'ў',
        "g'": 'ғ',
        'g‘': 'ғ',
        gʻ: 'ғ',
        sh: 'ш',
        ch: 'ч',
        yo: 'ё',
        yu: 'ю',
        ya: 'я',
        ng: 'нг',
    }
    const letters: Record<string, string> = {
        a: 'а',
        b: 'б',
        d: 'д',
        e: 'е',
        f: 'ф',
        g: 'г',
        h: 'ҳ',
        i: 'и',
        j: 'ж',
        k: 'к',
        l: 'л',
        m: 'м',
        n: 'н',
        o: 'о',
        p: 'п',
        q: 'қ',
        r: 'р',
        s: 'с',
        t: 'т',
        u: 'у',
        v: 'в',
        x: 'х',
        y: 'й',
        z: 'з',
    }
    return text.replace(/o['‘ʻ]|g['‘ʻ]|sh|ch|yo|yu|ya|ng|[a-z]/gi, (value) => {
        const translated =
            pairs[value.toLowerCase()] ?? letters[value.toLowerCase()] ?? value
        return value[0] === value[0].toUpperCase()
            ? translated[0].toUpperCase() + translated.slice(1)
            : translated
    })
}
