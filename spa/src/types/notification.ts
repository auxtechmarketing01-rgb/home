/**
 * One interface for both transports: exactly what GET /notifications returns per
 * row, and exactly what arrives over Pusher (03 section 4.2). Modelling the live
 * frame separately is how the two shapes start drifting.
 */
export interface AppNotification<TPayload = Record<string, unknown>> {
  id: string
  /** Short class name, e.g. `RewardEarnedNotification`. */
  type: string
  payload: TPayload
  /** Always null on a freshly broadcast frame. */
  read_at: string | null
  created_at: string
}
