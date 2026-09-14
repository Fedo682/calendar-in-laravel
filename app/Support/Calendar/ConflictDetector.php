<?php

namespace App\Support\Calendar;

use Illuminate\Support\Collection;

/**
 * Finds occurrences that overlap in time.
 *
 * Replaces a nested loop that compared every occurrence with every other one.
 * That is fine for a handful of events and quadratic once a recurring series
 * expands into hundreds, which is exactly what is coming.
 */
final class ConflictDetector
{
    /**
     * Every occurrence that overlaps at least one other, keyed by
     * Occurrence::key().
     *
     * Sweep line: sort by start, then walk forward keeping only the
     * occurrences still running. Because the list is sorted, the first
     * candidate that has already ended means every later one has too for this
     * subject, so the inner scan stops early instead of running to the end.
     *
     * @param  Collection<int, Occurrence>  $occurrences
     * @return array<string, list<Occurrence>>
     */
    public function detect(Collection $occurrences): array
    {
        $sorted = $occurrences->sortBy(fn (Occurrence $o) => $o->startsAt->getTimestamp())->values();

        /** @var array<string, list<Occurrence>> $conflicts */
        $conflicts = [];

        /** @var list<Occurrence> $active */
        $active = [];

        foreach ($sorted as $current) {
            // Drop everything that finished before this one began. Sorted by
            // start, so nothing dropped here can overlap anything later.
            $active = array_values(array_filter(
                $active,
                fn (Occurrence $open) => $open->endsAt > $current->startsAt,
            ));

            foreach ($active as $open) {
                if (! $current->overlaps($open)) {
                    continue;
                }

                $conflicts[$current->key()][] = $open;
                $conflicts[$open->key()][] = $current;
            }

            $active[] = $current;
        }

        return $conflicts;
    }

    /**
     * The subset of $candidates that clash with $subject.
     *
     * The subject is excluded from its own conflicts by key, so passing a set
     * that contains it is safe.
     *
     * @param  Collection<int, Occurrence>  $candidates
     * @return Collection<int, Occurrence>
     */
    public function conflictsFor(Occurrence $subject, Collection $candidates): Collection
    {
        return $candidates
            ->filter(fn (Occurrence $candidate) => $candidate->key() !== $subject->key()
                && $candidate->overlaps($subject))
            ->values();
    }
}
