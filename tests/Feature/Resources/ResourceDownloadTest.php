<?php

use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mentorship;
use App\Models\ResourceFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * FR-RES-01's other half. An audit found that a file could be uploaded, stored
 * and listed but never read back: `disk` and `path` are deliberately not
 * exposed in ResourceFileResource (02 §5), so no client had any way to reach
 * the blob.
 *
 * The download streams through the app rather than handing out a storage URL,
 * which is what keeps ResourceFilePolicy in the loop — a signed S3 link would
 * outlive the permission that produced it.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->owner = User::factory()->create();
    $this->goal = Goal::factory()->for($this->owner)->create();

    Sanctum::actingAs($this->owner);

    $path = tempnam(sys_get_temp_dir(), 'pathforge-dl');
    file_put_contents($path, "%PDF-1.4\n1 0 obj\ntrailer\n%%EOF\n");

    $this->post("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'file',
        'title' => 'Beej guide.pdf',
        'file' => new UploadedFile($path, 'beej.pdf', 'application/pdf', null, true),
    ])->assertCreated();

    $this->resource = ResourceFile::query()->sole();
});

it('streams the stored file back to the owner', function () {
    $response = $this->get("/api/v1/resources/{$this->resource->id}/download")->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('Beej guide.pdf')
        ->and($response->streamedContent())->toContain('%PDF-1.4');
});

/**
 * Reading a file delegates to the parent goal, exactly as listing does — so a
 * group peer on a shared goal can read it and a stranger cannot.
 */
it('refuses a member who cannot view the parent goal', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->get("/api/v1/resources/{$this->resource->id}/download")->assertForbidden();
});

it('lets a group peer download from a shared goal', function () {
    $peer = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $peer->id]);

    $this->goal->update(['visibility' => 'group', 'group_id' => $group->id]);

    Sanctum::actingAs($peer);

    $this->get("/api/v1/resources/{$this->resource->id}/download")->assertOk();
});

/**
 * FR-MENT-04: an accepted mentor reads a mentee's attachments too.
 */
it('lets an accepted mentor download from a private goal', function () {
    $mentor = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $mentor->id]);
    Mentorship::factory()->accepted()->between($mentor, $this->owner)->create();

    Sanctum::actingAs($mentor);

    $this->get("/api/v1/resources/{$this->resource->id}/download")->assertOk();
});

it('returns 404 for a link or note rather than pretending it has bytes', function () {
    $note = ResourceFile::factory()->note()->create([
        'resourceable_type' => Goal::class,
        'resourceable_id' => $this->goal->id,
    ]);

    $this->get("/api/v1/resources/{$note->id}/download")->assertNotFound();
});

it('returns 404 when the row survives but the blob is gone', function () {
    Storage::disk('local')->delete($this->resource->path);

    $this->get("/api/v1/resources/{$this->resource->id}/download")->assertNotFound();
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    $this->getJson("/api/v1/resources/{$this->resource->id}/download")->assertUnauthorized();
});
