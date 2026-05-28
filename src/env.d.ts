/// <reference types="vite/client" />

// Wird von vite.config.ts zur Build-Zeit injiziert (aus package.json)
declare const __APP_VERSION__: string

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}
