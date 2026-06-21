<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home redirects to the events listing', function () {
    $this->get(route('home'))->assertRedirect(route('events.index'));
});
