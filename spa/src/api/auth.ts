import type { AxiosRequestConfig } from 'axios'
import { apiClient, ensureCsrfCookie, getResource, sendResource } from './client'
import type { User, UserSettings } from '@/types/user'

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  timezone?: string
}

export interface ProfilePayload {
  name?: string
  timezone?: string
  avatar?: File | null
  settings?: UserSettings
}

export const authApi = {
  async login(payload: LoginPayload): Promise<User> {
    await ensureCsrfCookie()

    return sendResource<User>('post', '/login', payload)
  },

  async register(payload: RegisterPayload): Promise<User> {
    await ensureCsrfCookie()

    return sendResource<User>('post', '/register', payload)
  },

  async logout(): Promise<void> {
    await apiClient.post('/logout')
  },

  /** `config` exists so the cold-load session probe can set its own timeout. */
  me(config?: AxiosRequestConfig): Promise<User> {
    return getResource<User>('/user', config)
  },

  /**
   * Sent as multipart whenever an avatar is attached: Laravel cannot read an
   * uploaded file out of a JSON body, and PUT with FormData needs the
   * `_method` spoof to survive PHP's empty `$_POST` on non-POST multipart.
   */
  async updateProfile(payload: ProfilePayload): Promise<User> {
    if (payload.avatar instanceof File) {
      const form = new FormData()
      form.append('_method', 'PUT')
      form.append('avatar', payload.avatar)

      if (payload.name !== undefined) {
        form.append('name', payload.name)
      }

      if (payload.timezone !== undefined) {
        form.append('timezone', payload.timezone)
      }

      appendSettings(form, payload.settings)

      return sendResource<User>('post', '/user', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    const { avatar: _avatar, ...rest } = payload

    return sendResource<User>('put', '/user', rest)
  },

  async forgotPassword(email: string): Promise<string> {
    await ensureCsrfCookie()
    const response = await apiClient.post<{ message: string }>('/forgot-password', { email })

    return response.data.message
  },

  async resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<string> {
    await ensureCsrfCookie()
    const response = await apiClient.post<{ message: string }>('/reset-password', payload)

    return response.data.message
  },

  async resendVerification(): Promise<string> {
    const response = await apiClient.post<{ message: string }>('/email/verification-notification')

    return response.data.message
  },
}

function appendSettings(form: FormData, settings: UserSettings | undefined): void {
  if (!settings) {
    return
  }

  for (const [key, value] of Object.entries(settings)) {
    if (value === null) {
      form.append(`settings[${key}]`, '')
      continue
    }

    if (value !== undefined) {
      form.append(`settings[${key}]`, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
    }
  }
}
