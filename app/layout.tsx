import { Analytics } from '@vercel/analytics/next'
import type { Metadata, Viewport } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'Domain Intel | Collection Operations',
  description: 'Collect, validate, and understand the web at scale.',
  generator: 'domain-intel',
}

export const viewport: Viewport = {
  colorScheme: 'light',
  themeColor: '#f5f7fa',
}

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en" className="bg-background">
      <body className="antialiased">
        {children}
        {process.env.NODE_ENV === 'production' && <Analytics />}
      </body>
    </html>
  )
}
