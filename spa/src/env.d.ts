/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_BACKEND_URL: string
  readonly VITE_API_BASE_URL: string
  readonly VITE_PUSHER_APP_KEY: string
  readonly VITE_PUSHER_APP_CLUSTER: string
  readonly VITE_VAPID_PUBLIC_KEY: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>
  export default component
}

declare module 'vuedraggable' {
  import type { DefineComponent } from 'vue'
  const draggable: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>
  export default draggable
}
