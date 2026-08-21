/**
 * Clock form for a live timer: `25:00`, or `1:04:12` once it passes an hour.
 * Always tabular-width per segment so the digits never shift under a monospace
 * face -- a countdown that jitters is the most visible kind of cheap.
 */
export function formatClock(totalSeconds: number | null | undefined): string {
  if (totalSeconds === null || totalSeconds === undefined || Number.isNaN(totalSeconds)) {
    return '--:--'
  }

  const seconds = Math.max(0, Math.floor(totalSeconds))
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const rest = seconds % 60

  const pad = (value: number): string => String(value).padStart(2, '0')

  return hours > 0 ? `${hours}:${pad(minutes)}:${pad(rest)}` : `${pad(minutes)}:${pad(rest)}`
}

/** Prose form for totals: `4h 12m`, `12m`, `0m`. */
export function formatDuration(totalSeconds: number | null | undefined): string {
  if (!totalSeconds || totalSeconds < 0) {
    return '0m'
  }

  const seconds = Math.floor(totalSeconds)
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)

  if (hours > 0) {
    return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`
  }

  if (minutes > 0) {
    return `${minutes}m`
  }

  return `${seconds}s`
}

/** `90` -> `1h 30m`, for mentor-assigned and self-estimated minute figures. */
export function formatMinutes(minutes: number | null | undefined): string {
  if (minutes === null || minutes === undefined) {
    return '--'
  }

  return formatDuration(minutes * 60)
}

export function formatBytes(bytes: number | null | undefined): string {
  if (!bytes) {
    return '--'
  }

  const units = ['B', 'KB', 'MB', 'GB']
  let value = bytes
  let unit = 0

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit += 1
  }

  return `${value < 10 && unit > 0 ? value.toFixed(1) : Math.round(value)} ${units[unit]}`
}
