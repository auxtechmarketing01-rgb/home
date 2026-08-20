<?php

namespace App\Http\Requests;

use finfo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * FR-RES-01/02, and the upload hardening from 02 §8.
 *
 * Two independent gates, because either alone is insufficient:
 *
 * 1. `extensions` checks the *declared* extension against an allow-list.
 *    zip is deliberately absent — a common malware vector with no stated
 *    need here.
 * 2. A `finfo` sniff of the actual bytes, so a renamed executable handed a
 *    `.pdf` extension is rejected even though its extension is allowed.
 *
 * Laravel's `mimetypes` rule is *not* used, on purpose: it compares the
 * sniffed type against a flat allow-list, and a legitimate `.docx` sniffs as
 * `application/zip`, so it would reject real Office documents. The sniff
 * below handles that case explicitly instead of pretending it does not exist.
 */
class StoreResourceRequest extends FormRequest
{
    /**
     * The route runs `update` on the parent Goal or RoadmapItem.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::in(['file', 'link', 'note'])],
            'title' => ['required', 'string', 'max:255'],
            'file' => [
                $type === 'file' ? 'required' : 'nullable',
                'file',
                'max:'.(int) config('pathforge.uploads.max_size_kilobytes'),
                'extensions:'.implode(',', (array) config('pathforge.uploads.allowed_extensions')),
            ],
            'url' => [
                $type === 'link' ? 'required' : 'nullable',
                'url',
                'max:2048',
            ],
            'body' => [
                $type === 'note' ? 'required' : 'nullable',
                'string',
                'max:20000',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->assertContentMatchesAnAllowedType($validator);
        });
    }

    /**
     * The byte sniff. Reads the stored temporary file rather than trusting
     * the client's `Content-Type` header or the filename.
     */
    protected function assertContentMatchesAnAllowedType(Validator $validator): void
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return;
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return;
        }

        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($detected === false || $detected === '') {
            $validator->errors()->add('file', 'The file type could not be determined.');

            return;
        }

        if ($this->isAllowedContentType($detected, $file)) {
            return;
        }

        $validator->errors()->add(
            'file',
            "Files of type {$detected} are not allowed, whatever the file is named."
        );
    }

    protected function isAllowedContentType(string $detected, UploadedFile $file): bool
    {
        $allowed = (array) config('pathforge.uploads.allowed_mime_types');

        if (in_array($detected, $allowed, true)) {
            return true;
        }

        /**
         * Office formats are containers: `.docx`/`.pptx`/`.xlsx` really are
         * zip archives and the legacy `.doc`/`.xls`/`.ppt` really are OLE2
         * compound files, so many libmagic builds report the container type
         * rather than the document type. Accepting a container type only
         * when the declared extension is one of those formats keeps real
         * documents working without opening the door to arbitrary archives.
         */
        $containers = (array) config('pathforge.uploads.container_mime_types');
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($extension, (array) ($containers[$detected] ?? []), true);
    }
}
