<?php

use App\Enums\EventVisibility;
use App\Enums\RoleName;
use App\Enums\SyncDirection;

test('role names match the seeded role strings', function () {
    expect(RoleName::SuperAdmin->value)->toBe('super_admin')
        ->and(RoleName::Admin->value)->toBe('admin')
        ->and(RoleName::Member->value)->toBe('member');
});

test('role ranking orders super admin above admin above member', function () {
    expect(RoleName::SuperAdmin->isAtLeast(RoleName::Admin))->toBeTrue()
        ->and(RoleName::Admin->isAtLeast(RoleName::Member))->toBeTrue()
        ->and(RoleName::Admin->isAtLeast(RoleName::Admin))->toBeTrue()
        ->and(RoleName::Member->isAtLeast(RoleName::Admin))->toBeFalse();
});

test('tryFromName tolerates a user with no membership', function () {
    expect(RoleName::tryFromName(null))->toBeNull()
        ->and(RoleName::tryFromName('not_a_role'))->toBeNull()
        ->and(RoleName::tryFromName('admin'))->toBe(RoleName::Admin);
});

test('only public visibility reveals details to other viewers', function () {
    expect(EventVisibility::Public->revealsDetailsToOthers())->toBeTrue()
        ->and(EventVisibility::Private->revealsDetailsToOthers())->toBeFalse()
        ->and(EventVisibility::Busy->revealsDetailsToOthers())->toBeFalse();
});

test('visibility maps onto the icalendar CLASS property', function () {
    expect(EventVisibility::Public->icsClass())->toBe('PUBLIC')
        ->and(EventVisibility::Private->icsClass())->toBe('PRIVATE')
        ->and(EventVisibility::Busy->icsClass())->toBe('CONFIDENTIAL');
});

test('visibility maps onto the google calendar visibility field', function () {
    expect(EventVisibility::Public->googleVisibility())->toBe('public')
        ->and(EventVisibility::Private->googleVisibility())->toBe('private')
        ->and(EventVisibility::Busy->googleVisibility())->toBe('private');
});

test('sync direction reports which way events flow', function () {
    expect(SyncDirection::Pull->pulls())->toBeTrue()
        ->and(SyncDirection::Pull->pushes())->toBeFalse()
        ->and(SyncDirection::Push->pushes())->toBeTrue()
        ->and(SyncDirection::Push->pulls())->toBeFalse()
        ->and(SyncDirection::Both->pulls())->toBeTrue()
        ->and(SyncDirection::Both->pushes())->toBeTrue();
});
