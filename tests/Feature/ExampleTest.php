<?php

declare(strict_types = 1);

it('returns a successful response', function () {
    $user     = App\Models\User::factory()->create();
    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
});
