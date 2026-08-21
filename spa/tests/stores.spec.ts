import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useNotificationsStore } from '@/stores/notifications'
import { useRoadmapsStore } from '@/stores/roadmaps'
import { makeRoadmapItem } from './factories'
import type { AppNotification } from '@/types/notification'

vi.mock('@/api/roadmaps', () => ({
  roadmapsApi: {
    items: vi.fn(),
    createItem: vi.fn(),
    updateItem: vi.fn(),
    destroyItem: vi.fn(),
    reorder: vi.fn(),
    assign: vi.fn(),
  },
}))

const { roadmapsApi } = await import('@/api/roadmaps')

function frame(id: string, overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id,
    type: 'SprintExpiredNotification',
    payload: {},
    read_at: null,
    created_at: '2026-06-01T09:00:00.000Z',
    ...overrides,
  }
}

describe('notifications store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('receiveLive is idempotent by id, so a refetch and a frame cannot duplicate a row', () => {
    const store = useNotificationsStore()

    store.receiveLive(frame('a'))
    store.receiveLive(frame('a'))
    store.receiveLive(frame('b'))

    expect(store.items).toHaveLength(2)
    expect(store.unreadCount).toBe(2)
  })

  it('merges an updated frame into the existing row rather than prepending a second one', () => {
    const store = useNotificationsStore()

    store.receiveLive(frame('a'))
    store.receiveLive(frame('a', { read_at: '2026-06-01T10:00:00.000Z' }))

    expect(store.items).toHaveLength(1)
    expect(store.items[0]?.read_at).toBe('2026-06-01T10:00:00.000Z')
    expect(store.unreadCount).toBe(0)
  })

  it('prepends a genuinely new frame so the newest sits at the top', () => {
    const store = useNotificationsStore()

    store.receiveLive(frame('older'))
    store.receiveLive(frame('newer'))

    expect(store.items[0]?.id).toBe('newer')
  })

  it('tracks socket state without gating the list on it', () => {
    const store = useNotificationsStore()

    store.receiveLive(frame('a'))
    store.setSocketState('reconnecting')

    /** A dead socket costs freshness, not data. */
    expect(store.socketState).toBe('reconnecting')
    expect(store.items).toHaveLength(1)
  })
})

describe('roadmaps store reorder', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  const items = [
    makeRoadmapItem({ id: 1, position: 0, title: 'One' }),
    makeRoadmapItem({ id: 2, position: 1, title: 'Two' }),
    makeRoadmapItem({ id: 3, position: 2, title: 'Three' }),
  ]

  it('applies the new order optimistically and renumbers positions', async () => {
    vi.mocked(roadmapsApi.reorder).mockResolvedValue(undefined)

    const store = useRoadmapsStore()
    store.setItems(5, items)

    const reordered = [items[2], items[0], items[1]] as typeof items
    const ok = await store.reorder(5, reordered)

    expect(ok).toBe(true)
    expect(store.items(5).map((item) => item.id)).toEqual([3, 1, 2])
    expect(store.items(5).map((item) => item.position)).toEqual([0, 1, 2])
    expect(roadmapsApi.reorder).toHaveBeenCalledWith(5, [
      { id: 3, position: 0 },
      { id: 1, position: 1 },
      { id: 2, position: 2 },
    ])
  })

  it('rolls the whole batch back on failure rather than leaving a half-order', async () => {
    vi.mocked(roadmapsApi.reorder).mockRejectedValue(new Error('nope'))

    const store = useRoadmapsStore()
    store.setItems(5, items)

    const ok = await store.reorder(5, [items[2], items[0], items[1]] as typeof items)

    expect(ok).toBe(false)
    expect(store.items(5).map((item) => item.id)).toEqual([1, 2, 3])
    expect(store.failure?.message).toBeTruthy()
  })

  it('moves an item by one slot for the keyboard path', async () => {
    vi.mocked(roadmapsApi.reorder).mockResolvedValue(undefined)

    const store = useRoadmapsStore()
    store.setItems(5, items)

    await store.move(5, 1, 1)

    expect(store.items(5).map((item) => item.id)).toEqual([2, 1, 3])
  })

  it('refuses to move past the ends of the list', async () => {
    const store = useRoadmapsStore()
    store.setItems(5, items)

    expect(await store.move(5, 1, -1)).toBe(false)
    expect(await store.move(5, 3, 1)).toBe(false)
    expect(roadmapsApi.reorder).not.toHaveBeenCalled()
  })

  it('reverts an optimistic status change the server refuses', async () => {
    vi.mocked(roadmapsApi.updateItem).mockRejectedValue(new Error('forbidden'))

    const store = useRoadmapsStore()
    store.setItems(5, items)

    const target = store.items(5)[0]
    await store.setStatus(target!, 'done')

    expect(store.items(5)[0]?.status).toBe('todo')
  })
})
