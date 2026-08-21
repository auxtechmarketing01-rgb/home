<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SniffsUploadedFileContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    use SniffsUploadedFileContent;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-AUTH-04: name, avatar and timezone. `timezone` drives the streak
     * day boundary, so it is validated as a real IANA identifier.
     *
     * The avatar goes through the same two gates as every other upload
     * (02 §8): an extension allow-list plus a `finfo` byte sniff. An audit
     * found this path validating only `image|max:4096` — Laravel's `image`
     * rule trusts the guessed type, so an executable named `.png` could get
     * onto the disk. There is no reason for the weaker gate to exist.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'avatar' => [
                'sometimes',
                'nullable',
                'image',
                'max:'.(int) config('pathforge.uploads.avatar_max_size_kilobytes'),
                'extensions:'.implode(',', (array) config('pathforge.uploads.allowed_avatar_extensions')),
            ],
            'settings' => ['sometimes', 'array'],
            'settings.gamification_enabled' => ['sometimes', 'boolean'],
            'settings.sprint_reminder_hour' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:23'],
            'settings.streak_reminder_hour' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:23'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->assertContentMatchesAnAllowedType(
                $validator,
                'avatar',
                (array) config('pathforge.uploads.allowed_avatar_mime_types'),
            );
        });
    }
}
