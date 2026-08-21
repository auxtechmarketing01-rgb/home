<?php

namespace App\Http\Requests\Concerns;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * The `finfo` byte sniff from 02 §8, shared by every upload path.
 *
 * It exists because an extension allow-list is a name check, and a name is
 * whatever the client says it is. Only reading the bytes catches an executable
 * renamed to `.pdf`.
 *
 * Extracted into a trait after an audit found the avatar upload was the one
 * upload in the app validating `image|max:4096` and nothing else — a
 * second, weaker gate on the same class of input. One shared implementation
 * means hardening cannot drift between the two again.
 */
trait SniffsUploadedFileContent
{
    /**
     * @param  list<string>  $allowedMimeTypes
     * @param  array<string, list<string>>  $containerMimeTypes  sniffed type => extensions it is legitimate for
     */
    protected function assertContentMatchesAnAllowedType(
        Validator $validator,
        string $field,
        array $allowedMimeTypes,
        array $containerMimeTypes = [],
    ): void {
        $file = $this->file($field);

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return;
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return;
        }

        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($detected === false || $detected === '') {
            $validator->errors()->add($field, 'The file type could not be determined.');

            return;
        }

        if (in_array($detected, $allowedMimeTypes, true)) {
            return;
        }

        /**
         * Office formats are containers: `.docx`/`.pptx`/`.xlsx` really are zip
         * archives and legacy `.doc`/`.xls`/`.ppt` really are OLE2 compound
         * files, so many libmagic builds report the container rather than the
         * document. Accepting a container type only when the declared
         * extension is one of those formats keeps real documents working
         * without opening the door to arbitrary archives.
         */
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, (array) ($containerMimeTypes[$detected] ?? []), true)) {
            return;
        }

        $validator->errors()->add(
            $field,
            "Files of type {$detected} are not allowed, whatever the file is named."
        );
    }
}
