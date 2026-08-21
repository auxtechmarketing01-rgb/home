import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'
import type {
  ApiFailure,
  Paginated,
  PaginatedEnvelope,
  ResourceEnvelope,
  ValidationErrorBody,
} from '@/types/api'

const BACKEND_URL = import.meta.env.VITE_BACKEND_URL ?? 'http://localhost:8000'
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? `${BACKEND_URL}/api/v1`

export const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  /** Sanctum cookie SPA auth: the session cookie is the credential, so it must ride along. */
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete'])

let csrfReady: Promise<void> | null = null

/**
 * Sanctum hands out the XSRF-TOKEN cookie from a route outside `/api/v1`, so it
 * is fetched off the backend root rather than the api instance. Memoised on the
 * promise (not a boolean) so a burst of parallel mutations on a cold app shares
 * one round trip instead of racing several.
 */
export function ensureCsrfCookie(): Promise<void> {
  if (!csrfReady) {
    csrfReady = axios
      .get(`${BACKEND_URL}/sanctum/csrf-cookie`, { withCredentials: true })
      .then(() => undefined)
      .catch((error: unknown) => {
        csrfReady = null
        throw error
      })
  }

  return csrfReady
}

/** Forces the next mutation to re-bootstrap -- used after a logout drops the session. */
export function resetCsrfCookie(): void {
  csrfReady = null
}

apiClient.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase()

  if (MUTATING_METHODS.has(method)) {
    await ensureCsrfCookie()
  }

  return config
})

type UnauthorizedHandler = () => void

let onUnauthorized: UnauthorizedHandler | null = null

/**
 * Registered once by the auth store. Kept as a hook rather than importing the
 * store here, because a store importing the client that imports the store is a
 * cycle Vite resolves to `undefined` at exactly the wrong moment.
 */
export function setUnauthorizedHandler(handler: UnauthorizedHandler): void {
  onUnauthorized = handler
}

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    const status = error.response?.status

    /**
     * 419 means the session cookie outlived the CSRF token; clearing the memo
     * lets the next attempt re-bootstrap instead of failing forever.
     */
    if (status === 419) {
      resetCsrfCookie()
    }

    if (status === 401) {
      resetCsrfCookie()
      onUnauthorized?.()
    }

    return Promise.reject(error)
  },
)

/**
 * Normalises every transport failure into one shape so a view can render field
 * errors and a banner without branching on 422 vs 403 vs a dead network.
 */
export function toApiFailure(error: unknown, fallback = 'Something went wrong.'): ApiFailure {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status ?? null
    const body = error.response?.data as Partial<ValidationErrorBody> | undefined

    if (status === null) {
      return {
        status,
        message: 'Cannot reach the server. Check your connection and try again.',
        errors: {},
      }
    }

    if (status === 419) {
      return { status, message: 'Your session expired. Please try again.', errors: {} }
    }

    if (status === 403) {
      return {
        status,
        message: body?.message ?? 'You do not have access to that.',
        errors: {},
      }
    }

    if (status === 429) {
      return { status, message: 'Too many attempts. Wait a moment and retry.', errors: {} }
    }

    return {
      status,
      message: body?.message ?? fallback,
      errors: body?.errors ?? {},
    }
  }

  return { status: null, message: fallback, errors: {} }
}

export function isNotFound(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 404
}

/** GET returning a single `{ data }` resource. */
export async function getResource<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.get<ResourceEnvelope<T>>(url, config)

  return response.data.data
}

/** GET returning an unpaginated `{ data: [] }` collection. */
export async function getCollection<T>(url: string, config?: AxiosRequestConfig): Promise<T[]> {
  const response = await apiClient.get<ResourceEnvelope<T[]>>(url, config)

  return response.data.data
}

/** GET returning a paginated collection, flattened to `{ items, meta }`. */
export async function getPaginated<T>(
  url: string,
  config?: AxiosRequestConfig,
): Promise<Paginated<T>> {
  const response = await apiClient.get<PaginatedEnvelope<T>>(url, config)

  return { items: response.data.data, meta: response.data.meta }
}

export async function sendResource<T>(
  method: 'post' | 'put' | 'patch',
  url: string,
  payload?: unknown,
  config?: AxiosRequestConfig,
): Promise<T> {
  const response = await apiClient.request<ResourceEnvelope<T>>({
    method,
    url,
    data: payload,
    ...config,
  })

  return response.data.data
}

/**
 * Strips keys the caller left `undefined` so an untouched filter control does
 * not send `?status=undefined` and trip the backend `Rule::in` validation.
 */
export function toQuery(filters: Record<string, unknown> | undefined): Record<string, unknown> {
  if (!filters) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== null && value !== ''),
  )
}

export { BACKEND_URL, API_BASE_URL }
