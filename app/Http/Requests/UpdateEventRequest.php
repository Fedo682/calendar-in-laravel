<?php

namespace App\Http\Requests;

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Rules\ValidRRule;
use App\Support\Calendar\RecurrenceScope;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
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
            // Which part of a series this edit means. Absent is 'all', which
            // is also what a non-recurring event gets, so every existing
            // caller keeps working without sending it.
            'scope' => ['sometimes', Rule::in(RecurrenceScope::values())],
            // Required whenever the scope names a point in the series, since
            // "this occurrence" is not answerable without saying which.
            'occurrence_start' => [
                'nullable',
                'date',
                'required_if:scope,'.RecurrenceScope::ThisOccurrence->value,
                'required_if:scope,'.RecurrenceScope::ThisAndFollowing->value,
            ],
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

        // Instructions about *which* instances to write, not columns to write
        // to them. Leaving them in would mass-assign two attributes the
        // events table does not have.
        unset($validated['scope'], $validated['occurrence_start']);

        return $validated;
    }

    /**
     * Which part of a series this edit applies to.
     *
     * Defaults to the whole series: that is the only meaning a non-recurring
     * event can have, and it is what every caller that predates recurrence
     * was already asking for.
     */
    public function scope(): RecurrenceScope
    {
        return RecurrenceScope::tryFrom((string) $this->input('scope', ''))
            ?? RecurrenceScope::AllEvents;
    }

    /**
     * The RECURRENCE-ID this edit names, as a UTC instant.
     *
     * This is the instance's *original* start - where the rule put it - not
     * where the edit is moving it to. Anchoring on the original is what makes
     * the same edit idempotent when it is replayed, and is what iCalendar and
     * Google both key an override on.
     */
    public function occurrenceStart(): ?CarbonImmutable
    {
        $value = $this->input('occurrence_start');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->utc();
    }
}
