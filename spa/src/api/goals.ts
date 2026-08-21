import { apiClient, getCollection, getPaginated, getResource, sendResource, toQuery } from './client'
import type { Paginated } from '@/types/api'
import type { Category, Goal, GoalFilters, GoalPayload } from '@/types/goal'

export const goalsApi = {
  list(filters?: GoalFilters): Promise<Paginated<Goal>> {
    return getPaginated<Goal>('/goals', { params: toQuery(filters) })
  },

  show(id: number): Promise<Goal> {
    return getResource<Goal>(`/goals/${id}`)
  },

  create(payload: GoalPayload): Promise<Goal> {
    return sendResource<Goal>('post', '/goals', payload)
  },

  update(id: number, payload: Partial<GoalPayload>): Promise<Goal> {
    return sendResource<Goal>('put', `/goals/${id}`, payload)
  },

  complete(id: number): Promise<Goal> {
    return sendResource<Goal>('post', `/goals/${id}/complete`)
  },

  async destroy(id: number): Promise<void> {
    await apiClient.delete(`/goals/${id}`)
  },
}

export const categoriesApi = {
  list(): Promise<Category[]> {
    return getCollection<Category>('/categories')
  },

  create(payload: { name: string; icon?: string | null }): Promise<Category> {
    return sendResource<Category>('post', '/categories', payload)
  },
}
