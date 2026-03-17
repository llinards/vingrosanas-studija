<?php

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

test('can set and get a group of settings', function () {
    SiteSetting::setGroup('test', [
        'key1' => ['value' => 'value1', 'type' => 'string'],
        'key2' => ['value' => 'value2', 'type' => 'string'],
    ]);

    expect(SiteSetting::getValue('test', 'key1'))->toBe('value1');
    expect(SiteSetting::getValue('test', 'key2'))->toBe('value2');
});

test('getValue returns default when setting does not exist', function () {
    expect(SiteSetting::getValue('nonexistent', 'key', 'default'))->toBe('default');
});

test('getGroup returns empty collection for nonexistent group', function () {
    $group = SiteSetting::getGroup('nonexistent');

    expect($group)->toBeEmpty();
});

test('setGroup upserts existing settings', function () {
    SiteSetting::setGroup('test', [
        'key1' => ['value' => 'original', 'type' => 'string'],
    ]);

    SiteSetting::setGroup('test', [
        'key1' => ['value' => 'updated', 'type' => 'string'],
    ]);

    expect(SiteSetting::getValue('test', 'key1'))->toBe('updated');
    expect(SiteSetting::where('group', 'test')->where('key', 'key1')->count())->toBe(1);
});

test('setGroup busts the cache', function () {
    SiteSetting::setGroup('test', [
        'key1' => ['value' => 'cached', 'type' => 'string'],
    ]);

    // Access to populate cache
    SiteSetting::allGrouped();

    expect(Cache::has('site_settings'))->toBeTrue();

    SiteSetting::setGroup('test', [
        'key1' => ['value' => 'updated', 'type' => 'string'],
    ]);

    expect(Cache::has('site_settings'))->toBeFalse();
});

test('site helper function works', function () {
    SiteSetting::setGroup('helper_test', [
        'name' => ['value' => 'Test Name', 'type' => 'string'],
    ]);

    expect(site('helper_test', 'name'))->toBe('Test Name');
    expect(site('helper_test', 'missing', 'default'))->toBe('default');
});

test('site helper returns array for group without key', function () {
    SiteSetting::setGroup('group_test', [
        'a' => ['value' => '1', 'type' => 'string'],
        'b' => ['value' => '2', 'type' => 'string'],
    ]);

    $result = site('group_test');

    expect($result)->toBeArray();
    expect($result['a'])->toBe('1');
    expect($result['b'])->toBe('2');
});
