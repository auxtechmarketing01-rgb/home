<?php

use App\Models\Goal;
use App\Models\ResourceFile;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * FR-RES-01/02 and the upload hardening in 02 §8.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->owner = User::factory()->create();
    $this->goal = Goal::factory()->for($this->owner)->create();
    $roadmap = Roadmap::factory()->for($this->goal)->create();
    $this->item = RoadmapItem::factory()->for($roadmap)->create();

    Sanctum::actingAs($this->owner);
});

/**
 * A real file on disk with genuine magic bytes, because the whole point of
 * the content sniff is that it reads bytes rather than trusting a name.
 */
function uploadedFileWithContent(string $filename, string $content, string $clientMimeType): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'pathforge-test');
    file_put_contents($path, $content);

    return new UploadedFile($path, $filename, $clientMimeType, null, true);
}

it('attaches a pdf to a goal and stores it on the configured disk', function () {
    $file = uploadedFileWithContent(
        'beej-guide.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n%%EOF\n",
        'application/pdf',
    );

    $this->post("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'file',
        'title' => 'Beej networking guide',
        'file' => $file,
    ])->assertCreated()
        ->assertJsonPath('data.type', 'file')
        ->assertJsonPath('data.mime_type', 'application/pdf')
        /** Storage internals are never exposed to a client (02 §5). */
        ->assertJsonMissingPath('data.path')
        ->assertJsonMissingPath('data.disk');

    $resource = ResourceFile::query()->sole();

    expect($resource->disk)->toBe('local')
        ->and($resource->path)->not->toBeNull()
        ->and($resource->size_bytes)->toBeGreaterThan(0)
        ->and($resource->uploaded_by)->toBe($this->owner->id);

    Storage::disk('local')->assertExists($resource->path);
});

/**
 * 06 §1.2: the named test. A renamed executable handed a `.pdf` extension
 * passes every name-based check, so only the byte sniff catches it.
 */
it('rejects an executable renamed to look like a pdf', function () {
    $file = uploadedFileWithContent(
        'invoice.pdf',
        "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00"
        .str_repeat("\x00", 200).'This program cannot be run in DOS mode.',
        'application/pdf',
    );

    $this->post("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'file',
        'title' => 'Totally a pdf',
        'file' => $file,
    ])->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(ResourceFile::query()->count())->toBe(0);
});

it('rejects a disallowed extension outright', function () {
    $file = uploadedFileWithContent('payload.zip', 'PK'."\x03\x04".str_repeat("\x00", 40), 'application/zip');

    $this->post("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'file',
        'title' => 'Archive',
        'file' => $file,
    ])->assertUnprocessable()->assertJsonValidationErrors(['file']);
});

it('rejects an oversized file', function () {
    $maxKilobytes = (int) config('pathforge.uploads.max_size_kilobytes');

    $this->post("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'file',
        'title' => 'Too big',
        'file' => UploadedFile::fake()->create('huge.pdf', $maxKilobytes + 1024),
    ])->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(ResourceFile::query()->count())->toBe(0);
});

it('attaches a link to a roadmap item', function () {
    $this->postJson("/api/v1/roadmap-items/{$this->item->id}/resources", [
        'type' => 'link',
        'title' => 'C reference',
        'url' => 'https://en.cppreference.com/w/c',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'link')
        ->assertJsonPath('data.url', 'https://en.cppreference.com/w/c');

    $resource = ResourceFile::query()->sole();

    expect($resource->resourceable_id)->toBe($this->item->id)
        ->and($resource->resourceable_type)->toBe(RoadmapItem::class)
        ->and($resource->body)->toBeNull();
});

/**
 * FR-RES-02.
 */
it('attaches a freeform note', function () {
    $this->postJson("/api/v1/roadmap-items/{$this->item->id}/resources", [
        'type' => 'note',
        'title' => 'Gotcha',
        'body' => 'Arrays decay to pointers when passed to a function.',
    ])->assertCreated()->assertJsonPath('data.body', 'Arrays decay to pointers when passed to a function.');

    expect(ResourceFile::query()->sole()->url)->toBeNull();
});

/**
 * One table serves three kinds of attachment, so fields belonging to another
 * kind must not survive into the row and get rendered by the UI.
 */
it('clears fields that do not belong to the chosen type', function () {
    $this->postJson("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'note',
        'title' => 'Note with a stray url',
        'body' => 'The body is what matters.',
        'url' => 'https://example.test/ignored',
    ])->assertCreated();

    expect(ResourceFile::query()->sole()->url)->toBeNull();
});

it('validates the payload per type', function () {
    $this->postJson("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'link',
        'title' => 'No url given',
    ])->assertUnprocessable()->assertJsonValidationErrors(['url']);

    $this->postJson("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'note',
        'title' => 'No body given',
    ])->assertUnprocessable()->assertJsonValidationErrors(['body']);

    $this->postJson("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'file',
        'title' => 'No file given',
    ])->assertUnprocessable()->assertJsonValidationErrors(['file']);
});

it('lists attachments for a goal and for an item separately', function () {
    ResourceFile::factory()->create([
        'resourceable_type' => Goal::class,
        'resourceable_id' => $this->goal->id,
        'title' => 'On the goal',
    ]);

    ResourceFile::factory()->create([
        'resourceable_type' => RoadmapItem::class,
        'resourceable_id' => $this->item->id,
        'title' => 'On the item',
    ]);

    $this->getJson("/api/v1/goals/{$this->goal->id}/resources")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'On the goal');

    $this->getJson("/api/v1/roadmap-items/{$this->item->id}/resources")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'On the item');
});

it('deletes an attachment and removes the stored blob', function () {
    $file = uploadedFileWithContent('notes.txt', 'plain notes about pointers', 'text/plain');

    $this->post("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'file',
        'title' => 'Notes',
        'file' => $file,
    ])->assertCreated();

    $resource = ResourceFile::query()->sole();
    $path = $resource->path;

    $this->deleteJson("/api/v1/resources/{$resource->id}")->assertNoContent();

    $this->assertDatabaseMissing('resource_files', ['id' => $resource->id]);
    Storage::disk('local')->assertMissing($path);
});
