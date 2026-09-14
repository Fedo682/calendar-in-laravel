<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // 'sometimes' so a partial profile update (name and email only)
            // leaves the stored preference alone rather than being rejected;
            // 'required' still rejects an explicitly blank one. The column is
            // NOT NULL with a default, so a user always has a timezone.
            //
            // 'timezone:all' accepts any IANA identifier PHP knows about,
            // including the region-less ones (UTC) a browser can report.
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'week_starts_on' => ['integer', 'between:0,6'],
            'time_format' => ['in:12h,24h'],
            'theme' => ['in:system,light,dark'],
        ];
    }
}
