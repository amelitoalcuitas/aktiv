// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  app: {
    head: {
      title: 'Aktiv',
      meta: [
        {
          name: 'description',
          content: 'Aktiv - Find and join local activity groups'
        }
      ]
    }
  },
  ssr: false,
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE ?? '/api',
      googleMapsKey: process.env.NUXT_PUBLIC_GOOGLE_MAPS_KEY ?? 'DUMMY_KEY'
    }
  },
  modules: ['@nuxt/ui', '@pinia/nuxt', '@nuxt/image', '@nuxt/icon'],
  css: ['~/assets/css/main.css'],
  ui: {
    colorMode: false
  },
  icon: {
    localApiEndpoint: '/_nuxt_icon'
  }
});
