<?php

use App\Support\EventImages;

it('assigns multiple locally-served images per event', function () {
    $images = EventImages::for('11111111-2222-3333-4444-555555555555');

    expect($images)->toHaveCount(3);
    expect($images)->each->toStartWith('/images/events/');
});

it('is deterministic for the same event id', function () {
    expect(EventImages::for('same-id'))->toBe(EventImages::for('same-id'));
});
