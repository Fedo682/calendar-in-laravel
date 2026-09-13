<?php

use App\Support\Calendar\SyncContext;

afterEach(fn () => SyncContext::reset());

test('side effects are not suppressed by default', function () {
    expect(SyncContext::suppressed())->toBeFalse();
});

test('side effects are suppressed inside the callback only', function () {
    $inside = null;

    SyncContext::withoutSideEffects(function () use (&$inside) {
        $inside = SyncContext::suppressed();
    });

    expect($inside)->toBeTrue()
        ->and(SyncContext::suppressed())->toBeFalse();
});

test('the callback return value is passed through', function () {
    expect(SyncContext::withoutSideEffects(fn () => 'applied'))->toBe('applied');
});

test('nesting restores the outer suppressed state rather than clearing it', function () {
    SyncContext::withoutSideEffects(function () {
        SyncContext::withoutSideEffects(fn () => null);

        // A nested region ending must not re-enable outbound sync while the
        // outer region is still applying a remote change.
        expect(SyncContext::suppressed())->toBeTrue();
    });

    expect(SyncContext::suppressed())->toBeFalse();
});

test('a throwing callback still clears suppression', function () {
    expect(fn () => SyncContext::withoutSideEffects(fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class);

    expect(SyncContext::suppressed())->toBeFalse();
});
