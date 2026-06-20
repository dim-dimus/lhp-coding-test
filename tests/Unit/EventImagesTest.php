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

it('maps every event to image files that exist on disk', function () {
    foreach (EventImages::for('11111111-2222-3333-4444-555555555555') as $path) {
        expect(file_exists(dirname(__DIR__, 2).'/public'.$path))->toBeTrue();
    }
});
