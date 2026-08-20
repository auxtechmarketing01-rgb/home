---
paths:
  - app/Policies/**
---

# Authorization Policies

## GoalPolicy::view has three independent grants
Grant `view` to the owner, to a group member when `visibility === 'group'` and the viewer belongs to `goal.group_id`, and to a user holding an `accepted` mentorship with the owner (02 §5, FR-MENT-04). Keep the mentorship check as its own branch — it is an explicit read grant that must pass even for a `private` goal, never a side effect of shared group membership.

## Goal mutation is owner-only, with no mentor exception
`update` and `delete` are true for the owner alone (02 §5). A mentor who can `view` and `assign` still gets 403 on anything editing the mentee's own content, so the mentee keeps ownership of their plan and their claim of "I did this" (FR-MENT-06).

## Child records delegate upward and never widen
`RoadmapItemPolicy` `view`/`update`/`delete` defer to the parent Goal's policy, and `ResourceFilePolicy` defers to whichever parent (Goal or RoadmapItem) it is attached to (02 §5). A child record is never more permissive than the record it hangs off.

## Keep `assign` a distinct ability from `update`
`assign` (mentor setting an assigned time budget or due date, FR-MENT-05) is true only when the acting user is `mentor_id` on an `accepted` mentorship whose mentee owns the item's goal (02 §5). Folding it into `update` would also hand the mentor title/description edits and mark-done on the mentee's behalf — the exact boundary FR-MENT-06 draws.

## Sprints stay owner-only, always
`SprintPolicy` grants every ability to the owner and to nobody else: sprints are never group-visible, and a mentor neither views nor controls a mentee's sprints (02 §5, 04 Phase 2). Mentors and groups see sprint time only as aggregates, through goal stats and leaderboards.

## One ability per reward state transition
Give `create`, `request`, `respond`, `claim`, `fulfill`, and `revoke` their own policy methods instead of a shared `update` — the state machine stays correct only while each transition carries its own check (02 §5, 04 Phase 5). Bind each to its side and its source state: `claim` is mentee-only and only from `earned`; `respond`, `fulfill`, and `revoke` are mentor-only.

## Authorize every reward through its mentorship row
Resolve reward abilities via `rewards.mentorship_id`, requiring `status = 'accepted'` and the acting user on the correct side — mentor for `create`, mentee for `request` (FR-RWD-01, 02 §3, 02 §5).

## MentorshipPolicy: shared group in, other party responds
`create` requires the target to share at least one Group with the requester, and that check belongs in the Action as a real authorization rule rather than only in the Form Request as input validation (02 §5, FR-MENT-01). `respond` is open only to the party who did not initiate; `view` and `end` are open to either (FR-MENT-02, FR-MENT-07).

## Only `accepted` mentorships grant anything
Every mentorship-derived branch tests `status = 'accepted'`, so `pending`, `declined`, and `ended` rows grant no read access (02 §3). Ending a mentorship removes access going forward while leaving already-`fulfilled` rewards intact (FR-MENT-07).

## Mirror each policy branch with a query scope
Policies guard single-record routes; index endpoints, group comparison views, and leaderboards must scope their own queries so a `private` goal's data can never surface for another member (01 §5 Privacy NFR, FR-GRP-02). Add the matching scope whenever a policy branch changes, and keep the two in step.
