/** Mirrors UserResource. */
export interface User {
  id: number
  name: string
  email: string
  avatar_path: string | null
  timezone: string | null
  xp: number
  level: number
  gamification_enabled: boolean
  email_verified_at: string | null
}

/** The `users.settings` JSON column, as accepted by UpdateProfileRequest. */
export interface UserSettings {
  gamification_enabled?: boolean
  sprint_reminder_hour?: number | null
  streak_reminder_hour?: number | null
}

/** A member reduced to what a comparison or picker view may see (02 section 5). */
export interface UserSummary {
  id: number
  name: string
}
