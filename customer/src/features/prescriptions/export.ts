import type { Prescription } from '@/lib/api/types'
import { formatDate } from '@/lib/utils'
type Labels = { title: string; valid: string; expired: string; near: string; prism: string }
/** Render text, never HTML. Preserve every medical value and every line of notes. */
export function prescriptionImage(p: Prescription, labels: Labels): HTMLCanvasElement {
  const canvas = document.createElement('canvas')
  canvas.width = 1200
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Canvas unavailable')
  ctx.font = '28px sans-serif'
  const wrap = (text: string): string[] => text.split('\n').flatMap(paragraph => {
    const lines: string[] = []
    let line = ''
    for (const character of paragraph) {
      if (ctx.measureText(line + character).width > 1072 && line !== '') {
        lines.push(line)
        line = ''
      }
      line += character
    }
    lines.push(line)
    return lines
  })
  const details = [
    `PD: ${p.pd ?? '—'} · ${labels.near}: ${p.pd_near ?? '—'}`,
    `${labels.prism}: ${p.prism ?? '—'}`,
    `${labels.valid}: ${p.valid_until ? formatDate(p.valid_until) : '—'}${p.is_expired ? ` · ${labels.expired}` : ''}`,
    p.doctor, p.branch, p.notes ?? '',
  ].flatMap(wrap)
  canvas.height = Math.max(900, 480 + details.length * 42 + 80)
  ctx.fillStyle = '#184b99'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.fillStyle = '#ffffff'
  const line = (text: string, x: number, y: number, size = 28) => {
    ctx.font = `${size}px sans-serif`
    ctx.fillText(text, x, y)
  }
  line(`OPTIKA — ${labels.title}`, 64, 86, 40)
  line(`#${p.id} · ${formatDate(p.created_at)}`, 64, 140)
  const columns = [280, 500, 720, 940]
  ;['SPH', 'CYL', 'AXIS', 'ADD'].forEach((name, index) => line(name, columns[index], 230))
  for (const [index, eye] of [p.od, p.os].entries()) {
    const y = 310 + index * 80
    line(index === 0 ? 'OD' : 'OS', 64, y, 34)
    ;[eye.sph, eye.cyl, eye.axis, eye.add].forEach((value, column) => line(String(value ?? '—'), columns[column], y, 34))
  }
  details.forEach((text, index) => line(text, 64, 480 + index * 42))
  return canvas
}
export function downloadPrescription(canvas: HTMLCanvasElement, id: number) {
  const link = document.createElement('a')
  link.href = canvas.toDataURL('image/png')
  link.download = `OPTIKA-prescription-${id}.png`
  link.click()
}
export function printPrescription(canvas: HTMLCanvasElement) {
  const page = window.open('', '_blank')
  if (!page) { window.print(); return }
  page.document.title = 'OPTIKA'
  const image = page.document.createElement('img')
  image.style.width = '100%'
  image.alt = 'OPTIKA prescription'
  image.onload = () => { page.focus(); page.print() }
  image.src = canvas.toDataURL('image/png')
  page.document.body.append(image)
}
