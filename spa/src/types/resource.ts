import type { UserSummary } from './user'

export type ResourceType = 'file' | 'link' | 'note'

/** Mirrors ResourceFileResource (FR-RES-01/02). */
export interface ResourceFile {
  id: number
  type: ResourceType
  title: string
  url: string | null
  body: string | null
  mime_type: string | null
  size_bytes: number | null
  created_at: string | null
  uploaded_by?: UserSummary
}

export interface ResourcePayload {
  type: ResourceType
  title: string
  file?: File | null
  url?: string | null
  body?: string | null
}
