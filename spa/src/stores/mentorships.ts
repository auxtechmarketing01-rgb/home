import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { toApiFailure } from '@/api/client'
import { mentorshipsApi } from '@/api/mentorships'
import type { ApiFailure } from '@/types/api'
import type {
  MentorDashboardRow,
  Mentorship,
  MentorshipRequestPayload,
} from '@/types/mentorship'

export const useMentorshipsStore = defineStore('mentorships', () => {
  const items = ref<Mentorship[]>([])
  const dashboard = ref<MentorDashboardRow[]>([])

  const loading = ref(false)
  const saving = ref(false)
  const dashboardLoading = ref(false)
  const failure = ref<ApiFailure | null>(null)

  /**
   * Split into two lists rather than merged: the actions available differ per
   * side, so one list would have to re-derive `viewer_role` at every render.
   */
  const asMentee = computed(() => items.value.filter((entry) => entry.viewer_role === 'mentee'))
  const asMentor = computed(() => items.value.filter((entry) => entry.viewer_role === 'mentor'))

  const pendingForMe = computed(() => items.value.filter((entry) => entry.viewer_can_respond))

  /** Accepted mentorships are the only ones a reward may hang off. */
  const acceptedAsMentor = computed(() =>
    asMentor.value.filter((entry) => entry.status === 'accepted'),
  )
  const acceptedAsMentee = computed(() =>
    asMentee.value.filter((entry) => entry.status === 'accepted'),
  )

  async function fetchAll(): Promise<void> {
    loading.value = true
    failure.value = null

    try {
      items.value = await mentorshipsApi.list()
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load your mentorships.')
    } finally {
      loading.value = false
    }
  }

  async function fetchDashboard(): Promise<void> {
    dashboardLoading.value = true

    try {
      dashboard.value = await mentorshipsApi.dashboard()
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load the mentor dashboard.')
    } finally {
      dashboardLoading.value = false
    }
  }

  async function request(payload: MentorshipRequestPayload): Promise<Mentorship | null> {
    return mutate(async () => {
      const mentorship = await mentorshipsApi.request(payload)
      items.value = [mentorship, ...items.value]

      return mentorship
    }, 'Could not send that request.')
  }

  async function accept(id: number): Promise<Mentorship | null> {
    return mutate(() => mentorshipsApi.accept(id).then(apply), 'Could not accept that request.')
  }

  async function decline(id: number): Promise<Mentorship | null> {
    return mutate(() => mentorshipsApi.decline(id).then(apply), 'Could not decline that request.')
  }

  async function end(id: number): Promise<Mentorship | null> {
    return mutate(() => mentorshipsApi.end(id).then(apply), 'Could not end that mentorship.')
  }

  function apply(mentorship: Mentorship): Mentorship {
    items.value = items.value.map((entry) => (entry.id === mentorship.id ? mentorship : entry))

    return mentorship
  }

  async function mutate<T>(action: () => Promise<T>, message: string): Promise<T | null> {
    saving.value = true
    failure.value = null

    try {
      return await action()
    } catch (error) {
      failure.value = toApiFailure(error, message)

      return null
    } finally {
      saving.value = false
    }
  }

  function clearFailure(): void {
    failure.value = null
  }

  return {
    items,
    dashboard,
    loading,
    saving,
    dashboardLoading,
    failure,
    asMentee,
    asMentor,
    pendingForMe,
    acceptedAsMentor,
    acceptedAsMentee,
    fetchAll,
    fetchDashboard,
    request,
    accept,
    decline,
    end,
    clearFailure,
  }
})
