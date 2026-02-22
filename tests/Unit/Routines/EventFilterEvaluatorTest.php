<?php

use App\Services\Routines\EventFilterEvaluator;

beforeEach(function () {
    $this->evaluator = new EventFilterEvaluator;
});

test('exact match passes', function () {
    $result = $this->evaluator->evaluate(
        ['status' => 'active'],
        ['status' => 'active', 'name' => 'test'],
    );

    expect($result)->toBeTrue();
});

test('exact match fails on mismatch', function () {
    $result = $this->evaluator->evaluate(
        ['status' => 'active'],
        ['status' => 'inactive'],
    );

    expect($result)->toBeFalse();
});

test('glob wildcard matches', function () {
    $result = $this->evaluator->evaluate(
        ['email' => '*@example.com'],
        ['email' => 'user@example.com'],
    );

    expect($result)->toBeTrue();
});

test('glob wildcard fails on non-match', function () {
    $result = $this->evaluator->evaluate(
        ['email' => '*@example.com'],
        ['email' => 'user@other.com'],
    );

    expect($result)->toBeFalse();
});

test('existence check passes when key exists', function () {
    $result = $this->evaluator->evaluate(
        ['user_id' => '__exists__'],
        ['user_id' => 42],
    );

    expect($result)->toBeTrue();
});

test('existence check fails when key missing', function () {
    $result = $this->evaluator->evaluate(
        ['user_id' => '__exists__'],
        ['name' => 'test'],
    );

    expect($result)->toBeFalse();
});

test('nested dot notation works', function () {
    $result = $this->evaluator->evaluate(
        ['data.user.role' => 'admin'],
        ['data' => ['user' => ['role' => 'admin']]],
    );

    expect($result)->toBeTrue();
});

test('empty filter matches everything', function () {
    $result = $this->evaluator->evaluate([], ['any' => 'data']);

    expect($result)->toBeTrue();
});

test('multiple conditions use AND logic', function () {
    $result = $this->evaluator->evaluate(
        ['status' => 'active', 'type' => 'user'],
        ['status' => 'active', 'type' => 'user'],
    );

    expect($result)->toBeTrue();

    $result = $this->evaluator->evaluate(
        ['status' => 'active', 'type' => 'admin'],
        ['status' => 'active', 'type' => 'user'],
    );

    expect($result)->toBeFalse();
});
