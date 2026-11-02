import { NextResponse, type NextRequest } from 'next/server';

/** Root layouts do not receive child segment params; derive HTML language on the server. */
export function proxy(request:NextRequest) {
  const locale=request.nextUrl.pathname.split('/')[1];
  const headers=new Headers(request.headers);
  headers.set('x-optika-locale',['uz-latn','uz-cyrl','ru','en'].includes(locale)?locale:'uz-latn');
  return NextResponse.next({request:{headers}});
}
export const config={matcher:['/uz-latn/:path*','/uz-cyrl/:path*','/ru/:path*','/en/:path*']};
