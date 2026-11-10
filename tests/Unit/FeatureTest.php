<?php

namespace Tests\Unit;

use App\Enums\Feature;
use PHPUnit\Framework\TestCase;

class FeatureTest extends TestCase
{
    public function test_values_are_snake_case_and_unique(): void
    {
        $values = Feature::values();

        $this->assertSame($values, array_unique($values));

        foreach ($values as $value) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', (string) $value);
        }
    }

    public function test_every_case_has_a_distinct_label(): void
    {
        $labels = array_map(fn (Feature $feature) => $feature->label(), Feature::cases());

        foreach ($labels as $label) {
            $this->assertNotSame('', trim($label));
        }

        $this->assertSame($labels, array_unique($labels));
    }

    public function test_values_helper_matches_the_cases(): void
    {
        $this->assertSame(
            array_map(fn (Feature $feature) => $feature->value, Feature::cases()),
            Feature::values(),
        );
    }
}
