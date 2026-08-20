---
paths:
  - app/Notifications/**
  - routes/channels.php
  - config/broadcasting.php
  - app/Http/Controllers/Api/NotificationController.php
  - app/Http/Resources/NotificationResource.php
---

# Notifications And Real-Time Delivery

## Every notification extends MemberNotification and defines only toArray()
The abstract base owns the channel set, so `database` + `broadcast` can never be forgotten. Do not hand-write a `via()` in a subclass; to reach a closed browser, override `reachesClosedBrowser()` and add `toWebPush()` (02 §10.2, FR-NOT-03).

## Durable first, live second
The `notifications` row is the record; broadcasting is queued and best-effort. A Pusher failure must degrade the app to "not live," never to "notification lost" — so never send something on `broadcast` alone, and never treat a broadcast error as a job failure (01 NFR Real-time delivery).

## Web push is opted into, one notification at a time
Pusher reaches an open tab; Web Push reaches a closed browser and costs the member an OS-level interruption. Sprint expiry, a reward being claimed or fulfilled, a mentorship request — yes. A streak reminder — no (02 §10.1, FR-SPR-10).

## The payload is nested under `payload`, never flat and never `data`
Laravel merges `id` and `type` into the broadcast frame, so a flat payload lets a notification's own field shadow one of them. And `data` is the API-resource wrapper key: a top-level `data` field makes `JsonResource` treat the body as pre-wrapped and silently drop the envelope. `NotificationResource` and `toBroadcast()` therefore expose the same `{id, type, payload, read_at, created_at}` shape, field for field (02 §10.2).

## broadcastType() keeps PHP namespaces off the wire
It returns `class_basename($this)`, so the SPA switches on `RewardEarnedNotification` rather than a FQCN. `NotificationResource` applies the same `class_basename` to the stored `type` column so both transports agree.

## A channel is an authorization boundary, not plumbing
Subscribing to another member's private channel leaks their notifications regardless of any Policy. Every channel in `routes/channels.php` is private, returns a hard boolean, and mirrors the Policy it corresponds to — `App.Models.User.{id}` is self-only, `groups.{group}` mirrors `GroupPolicy::view`, `mentorships.{mentorship}` mirrors `MentorshipPolicy::view` and must stop granting access once the row is `ended` (FR-MENT-07, 02 §10.3).

## Add a channel when something broadcasts on it
Not in advance. An authorized channel nobody publishes to is dead code that still widens the surface.

## The channel-auth route belongs in the versioned stateful API group
`withBroadcasting(..., attributes: ['prefix' => 'api/v1', 'middleware' => ['api', 'auth:sanctum']])`. The framework default mounts it on the `web` group, which a separate-origin SPA using Sanctum cookie auth cannot reach — and the failure looks like a broken authorization rule, not a routing mistake (02 §10.3).

## Broadcast::channel() binds to the broadcaster instance, not to config
Switching `broadcasting.default` after boot leaves the new instance with zero channels, and a broadcaster with zero channels rejects everything. Any test that switches the driver must re-require `routes/channels.php` (02 §10.3).

## The Pusher secret is server-only
`PUSHER_APP_SECRET` lives in `.env` and nowhere else — not in `.env.example`, not in the repo, and never behind a `VITE_` prefix, since everything so prefixed is compiled into the shipped bundle. Only the key and cluster are public.

## Notification endpoints are self-scoped, so they 404
`/notifications` and `/notifications/{id}/read` carry no Policy: they start from `$request->user()->notifications()`, so another member's id resolves to nothing and returns 404. That is deliberate and distinct from the 403 the policy-guarded routes return — don't "fix" it into a 403 (02 §4).

## Tests need the pusher driver to assert anything about channels
The suite runs on `BROADCAST_CONNECTION=null`, and the null broadcaster never invokes channel callbacks. A channel-authorization test left on it passes while asserting nothing — configure `pusher` with dummy credentials instead (HMAC only, no network) (06 §1.2).
