---
paths:
  - app/Http/Controllers/**
  - app/Http/Requests/**
  - app/Http/Resources/**
  - routes/api.php
---

# Api Controllers Requests And Resources

## Controller methods are three steps
Every method is `validate -> call an Action/Service -> return a Resource` (02 §4). Query building and business logic belong in `app/Actions/<Domain>/` or `app/Services`, so a controller body that grows an Eloquent chain or a conditional is a signal the Action is missing.

## Version the API prefix from day one
Register all API routes under `/api/v1/...` even while only one version exists — cheap now, expensive to retrofit (04 Cross-cutting).

## Form Request and Policy exist before the route does
A new endpoint gets its Form Request and its Policy ability first, then the `routes/api.php` entry (04 Cross-cutting). Authorize the specific ability the route table names — e.g. `assign` on a roadmap item, deliberately distinct from `update` so a mentor never gains edit or delete rights (02 §5, FR-MENT-06).

## Endpoints with no Policy still scope to the acting user
`/sprints`, `/sprints/export`, `/analytics/overview`, and `/notifications` are marked "scoped to self" rather than policy-checked, so constrain the query to `auth()->id()` inside the Action (02 §4). An empty Policy column means self-scoped, never unscoped.

## Resources enumerate every returned field
List fields explicitly in `toArray()` and expose relations through `whenLoaded()` / `whenCounted()` (03 §2). Returning a model or its full attribute bag leaks `time_spent_seconds`, `xp`, and other users' internal IDs through relations (02 §5).

## Each Resource has a matching TypeScript interface
Add or update the mirrored interface in the SPA's `types/` whenever a Resource's shape changes, and flag drift in review (03 §2). Serialize dates as ISO strings (`?->toDateString()`) since the frontend types them as `string | null` and parses at the edge.

## The file model is ResourceFile, never Resource
`ResourceFile` (model) / `ResourceFileResource` (API resource) / `ResourceController` keeps the domain "Resource" apart from Laravel's `Http\Resources` concept — flag any reintroduction of `Resource.php` as a model (02 §2 naming note).

## Uploads are validated by MIME allow-list plus a byte sniff
`StoreResourceRequest` checks an allow-list (pdf, common image types, docx/pptx/xlsx, plain text), a configurable max size (e.g. 25 MB), and a `finfo` content check to catch spoofed extensions (02 §8). Keep zip out of the allow-list — a malware vector with no stated need (02 §8).

## Cross-column rules live in the Form Request, authorization rules do not
Enforce "at least one of `goal_id`/`roadmap_item_id`" in `StoreRewardRequest`, since that is not cleanly expressible as one migration constraint (02 §3 rewards). Real authorization rules go to the Policy and the Action instead — the shared-Group requirement for mentorship creation is checked in the Action, not the Form Request (02 §5, FR-MENT-01).

## Rate-limit the auth and sprint-start endpoints
Apply throttling to `/login`, `/register`, and `/sprints/start` (04 Cross-cutting, 01 NFR Security).

## Keep "how the session is established" separable in AuthController
Sanctum SPA cookie auth is the current choice; a later native mobile client will need token auth from the same controller, so isolate the session-establishment step from the rest of the auth logic (02 §1).
