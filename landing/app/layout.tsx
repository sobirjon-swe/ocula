import type { Metadata } from "next";
import { headers } from "next/headers";
import { isLocale } from "@/lib/i18n";
import { Geist, Geist_Mono } from "next/font/google";


import { site } from "@/lib/site";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  metadataBase: new URL(site.url),
  title: {
    default: `${site.name} — ${site.tagline}`,
    template: `%s — ${site.name}`,
  },
  description: site.description,
  openGraph: {
    title: `${site.name} — ${site.tagline}`,
    description: site.description,
    siteName: site.name,
    locale: "uz_UZ",
    type: "website",
  },
};

export default async function RootLayout({ children }: {children:React.ReactNode}) {
  const language=(await headers()).get('x-optika-locale')??'uz-latn';
  const locale=isLocale(language)?language:'uz-latn';
  return (
    <html
      lang={locale??"uz-latn"}
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col">

        {children}

      </body>
    </html>
  );
}
