<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected when accessing manage assets page', function () {
    $response = $this->get(route('manage-assets'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can access manage assets page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('manage-assets'));

    $response->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('manage-assets'),
        );
});
