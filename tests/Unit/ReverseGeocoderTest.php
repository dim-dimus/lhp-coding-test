<?php

use App\Support\ReverseGeocoder;

it('maps a jittered coordinate to its nearest city', function () {
    // The New York anchor is 40.7128, -74.0060; events jitter within ±0.5°.
    $label = ReverseGeocoder::label(40.9, -74.3);

    expect($label)->not->toBeNull()
        ->and($label['display'])->toBe('New York, USA');
});

it('returns null when coordinates are missing', function () {
    expect(ReverseGeocoder::label(null, null))->toBeNull();
});

it('produces a bounding box that contains the matching anchor', function () {
    $bbox = ReverseGeocoder::bbox('New York, USA');

    expect($bbox)->not->toBeNull();
    expect(40.7128)->toBeGreaterThanOrEqual($bbox['min_lat'])->toBeLessThanOrEqual($bbox['max_lat']);
    expect(-74.0060)->toBeGreaterThanOrEqual($bbox['min_lng'])->toBeLessThanOrEqual($bbox['max_lng']);
});

it('returns null bbox for an unknown city', function () {
    expect(ReverseGeocoder::bbox('Atlantis, Nowhere'))->toBeNull();
});

it('lists cities for the location filter', function () {
    $labels = collect(ReverseGeocoder::cities())->pluck('label');

    expect($labels)->toContain('New York, USA', 'London, United Kingdom', 'Tokyo, Japan');
});
