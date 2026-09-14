<?php

namespace App\Http\Requests;

use App\Enums\EventVisibility;
use App\Models\Calendar;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * Authorization is handled by the controller's authorize() call, which
     * has the calendar in hand.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            // after_or_equal rather than after: a zero-length marker at a point
            // in time is legitimate, and this preserves existing behaviour.
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'visibility' => ['sometimes', Rule::enum(EventVisibility::class)],
        ];
    }

    /**
     * Validated attributes with the calendar's default visibility filled in.
     *
     * The default is resolved here rather than in an observer so that it
     * applies exactly where a caller could have supplied a value, and so an
     * explicit visibility - from a form, an import, or a sync - is never
     * silently overridden.
     *
     * @return array<string, mixed>
     */
    public function attributesFor(Calendar $calendar): array
    {
        $validated = $this->validated();

        $validated['visibility'] ??= $calendar->defaultEventVisibility()->value;

        return $validated;
    }
}
