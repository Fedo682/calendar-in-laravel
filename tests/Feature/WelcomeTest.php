<?php

use Inertia\Testing\AssertableInertia;

test('the landing page renders for a guest', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->has('canLogin')
            ->has('canRegister')
        );
});
