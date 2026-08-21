import { format, formatDistanceToNowStrict, isValid, parseISO } from 'date-fns'

/**
 * Every date crossing the API boundary is an ISO string; these helpers are the
 * only place it becomes a Date. The server stays authoritative for day-boundary
 * logic (streaks, heatmaps) -- this is display formatting only.
 */
export function toDate(value: string | null | undefined): Date | null {
  if (!value) {
    return null
  }

  const parsed = parseISO(value)

  return isValid(parsed) ? parsed : null
}

export function formatDate(value: string | null | undefined, pattern = 'd MMM yyyy'): string {
  const date = toDate(value)

  return date ? format(date, pattern) : '--'
}

export function formatDateTime(value: string | null | undefined): string {
  return formatDate(value, "d MMM yyyy 'at' HH:mm")
}

export function formatShortDate(value: string | null | undefined): string {
  return formatDate(value, 'd MMM')
}

/** `3 days ago`, `in 2 hours`. Used for notification rows and due dates. */
export function formatRelative(value: string | null | undefined): string {
  const date = toDate(value)

  if (!date) {
    return ''
  }

  return formatDistanceToNowStrict(date, { addSuffix: true })
}

/** Today in the browser's zone as `yyyy-MM-dd`, matching the trend point keys. */
export function todayIsoDate(): string {
  return format(new Date(), 'yyyy-MM-dd')
}

export function isPast(value: string | null | undefined): boolean {
  const date = toDate(value)

  return date !== null && date.getTime() < Date.now()
}
