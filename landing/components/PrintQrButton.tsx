"use client";
export function PrintQrButton({ image, title, url, label }: {image:string;title:string;url:string;label:string}) {
  return <button type="button" className="min-h-11 underline underline-offset-4" onClick={() => {
    const page=window.open('', '_blank'); if(!page) { window.print(); return; }
    page.document.title=title;
    const heading=page.document.createElement('h1');heading.textContent=title;
    const qr=page.document.createElement('img');qr.width=320;qr.height=320;qr.alt=title;
    const link=page.document.createElement('p');link.textContent=url;
    page.document.body.append(heading,qr,link);
    qr.onload=()=>{page.focus();page.print()};qr.src=image;
  }}>{label}</button>
}
