import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'ORP Developer Portal',
  description: 'Dokumentasi pengembang Open Radiology Platform',
  lang: 'id-ID',
  themeConfig: {
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Arsitektur', link: '/architecture' },
      { text: 'Kontribusi', link: '/contributing' },
    ],
    sidebar: [
      {
        text: 'Panduan',
        items: [
          { text: 'Home', link: '/' },
          { text: 'Arsitektur', link: '/architecture' },
          { text: 'Kontribusi', link: '/contributing' },
        ],
      },
    ],
  },
})
