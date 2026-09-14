<?php

namespace App\Http\Requests;

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Rules\ValidRRule;
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
            // Stored raw. The allowlist inside ValidRRule is what keeps the
            // corpus inside what the ICS feed and Google's recurrence[] array
            // can both round-trip, and what refuses an expansion bomb at the
            // door rather than truncating it silently later.
            'recurrence_rule' => ['nullable', 'string', 'max:512', new ValidRRule],
            // Mandatory for anything recurring, and the reason DST works. A
            // rule anchored at 09:00 Europe/Berlin has to stay at 09:00 when
            // the offset changes, which cannot be recovered from a UTC
            // instant alone - so a rule without a zone is rejected rather
            // than defaulted.
            'recurrence_timezone' => ['nullable', 'required_with:recurrence_rule', 'string', 'timezone'],
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
