import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Open Radiology Platform',
  description: 'PACS RIS AI terpadu — docs developer',
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
          { text: 'Ringkasan', link: '/' },
          { text: 'Arsitektur', link: '/architecture' },
          { text: 'Kontribusi', link: '/contributing' },
        ],
      },
      {
        text: 'Produk & API',
        items: [
          { text: 'OMC API', link: '/products/omc' },
          { text: 'RIS API & FHIR', link: '/products/ris' },
          { text: 'AI Service', link: '/products/ai' },
          { text: 'Integration (MORBIS/MWL/HL7)', link: '/products/integration' },
        ],
      },
    ],
  },
})
