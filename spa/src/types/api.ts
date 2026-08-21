/**
 * Envelopes every Laravel API Resource response arrives in. Nothing in a store
 * or component unwraps `response.data.data` by hand -- the api layer does it,
 * and these types are what make that safe.
 */
export interface ResourceEnvelope<T> {
  data: T
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  path: string
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedEnvelope<T> {
  data: T[]
  links: PaginationLinks
  meta: PaginationMeta
}

export interface Paginated<T> {
  items: T[]
  meta: PaginationMeta
}

/** Laravel 422 body. `errors` is keyed by field path, e.g. `settings.gamification_enabled`. */
export interface ValidationErrorBody {
  message: string
  errors: Record<string, string[]>
}

/**
 * The single failure shape every store surfaces. Normalising here is what lets a
 * view render field errors and a banner without knowing whether it got a 422, a
 * 403, or a dead socket.
 */
export interface ApiFailure {
  status: number | null
  message: string
  errors: Record<string, string[]>
}
