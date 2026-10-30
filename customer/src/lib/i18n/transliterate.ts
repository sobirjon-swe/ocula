/** Derived Uzbek Cyrillic; only Latin source translations are maintained. */
export function toCyrillic(text: string): string {
 const pairs: Record<string,string> = {"o'":"ў", "g'":"ғ", sh:'ш',ch:'ч',"ng'":'нғ',ng:'нг',yo:'ё',yu:'ю',ya:'я',ye:'е',ts:'ц'}
 const letters: Record<string,string> = {a:'а',b:'б',d:'д',e:'е',f:'ф',g:'г',h:'ҳ',i:'и',j:'ж',k:'к',l:'л',m:'м',n:'н',o:'о',p:'п',q:'қ',r:'р',s:'с',t:'т',u:'у',v:'в',x:'х',y:'й',z:'з',"'":'ъ'}
 return text.replace(/[ʻ’‘`ʼ]/g, "'").replace(/\b(?:OPTIKA|SPH|CYL|AXIS|ADD|PD|OD|OS)\b|o'|g'|ng'|sh|ch|ng|yo|yu|ya|ye|ts|[a-z']/gi, token => {
 if (/^(OPTIKA|SPH|CYL|AXIS|ADD|PD|OD|OS)$/.test(token)) return token
 const result = pairs[token.toLowerCase()] ?? letters[token.toLowerCase()] ?? token
 return /[A-Z]/.test(token) && token === token.toUpperCase() ? result.toUpperCase() : /[A-Z]/.test(token[0]) ? result[0].toUpperCase() + result.slice(1) : result
 })
}
