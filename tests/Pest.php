<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Load an API contract fixture as an associative array.
 *
 * @return array<string, mixed>
 */
function apiFixture(string $name): array
{
    $path = base_path("tests/Fixtures/api/{$name}");

    if (! file_exists($path)) {
        throw new RuntimeException("Fixture not found: {$path}");
    }

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * Recursively assert that $actual has the same keys and value types as $expected.
 *
 * Used to verify Resource output matches the contract anchor without coupling
 * tests to fixture-specific data values (which differ between the production
 * DB and the test DB).
 *
 * @param  mixed  $expected
 * @param  mixed  $actual
 */
function assertJsonShapeMatches($expected, $actual, string $path = ''): void
{
    if (is_array($expected) && array_is_list($expected)) {
        expect($actual)->toBeArray("Path '{$path}': expected list");
        if (count($expected) > 0 && count($actual) > 0) {
            assertJsonShapeMatches($expected[0], $actual[0], "{$path}[0]");
        }

        return;
    }

    if (is_array($expected)) {
        expect($actual)->toBeArray("Path '{$path}': expected object");
        $expectedKeys = array_keys($expected);
        $actualKeys = is_array($actual) ? array_keys($actual) : [];
        sort($expectedKeys);
        sort($actualKeys);
        expect($actualKeys)->toEqual(
            $expectedKeys,
            "Path '{$path}': key set mismatch.",
        );
        foreach ($expected as $key => $value) {
            assertJsonShapeMatches($value, $actual[$key] ?? null, "{$path}.{$key}");
        }

        return;
    }

    if ($expected === null || $actual === null) {
        // Either side null is allowed: every leaf in the fixture is documented as nullable
        // because the underlying DB columns mostly are. The strict assertion is "key set
        // matches" — which is enforced at the array branch above.
        return;
    }

    expect(gettype($actual))->toEqual(
        gettype($expected),
        "Path '{$path}': scalar type mismatch.",
    );
}
