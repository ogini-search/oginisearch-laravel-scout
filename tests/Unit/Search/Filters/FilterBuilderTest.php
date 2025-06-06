<?php

namespace OginiScoutDriver\Tests\Unit\Search\Filters;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Search\Filters\FilterBuilder;

class FilterBuilderTest extends TestCase
{
    /** @test */
    public function it_can_add_term_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->term('status', 'published');

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'term',
            'field' => 'status',
            'value' => 'published',
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_terms_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->terms('category', ['electronics', 'computers']);

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'terms',
            'field' => 'category',
            'values' => ['electronics', 'computers'],
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_range_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->range('price', ['gte' => 100, 'lte' => 500]);

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'range',
            'field' => 'price',
            'range' => ['gte' => 100, 'lte' => 500],
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_greater_than_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->greaterThan('price', 100, true);

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'range',
            'field' => 'price',
            'range' => ['gte' => 100],
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_less_than_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->lessThan('price', 500, false);

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'range',
            'field' => 'price',
            'range' => ['lt' => 500],
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_between_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->between('price', 100, 500, true);

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'range',
            'field' => 'price',
            'range' => ['gte' => 100, 'lte' => 500],
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_exists_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->exists('description');

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'exists',
            'field' => 'description',
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_missing_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->missing('optional_field');

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'missing',
            'field' => 'optional_field',
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_prefix_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->prefix('title', 'iPhone');

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'prefix',
            'field' => 'title',
            'value' => 'iPhone',
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_wildcard_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->wildcard('title', '*phone*');

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'wildcard',
            'field' => 'title',
            'value' => '*phone*',
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_geo_distance_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->geoDistance('location', ['lat' => 40.7128, 'lon' => -74.0060], '10km');

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals([
            'type' => 'geo_distance',
            'field' => 'location',
            'location' => ['lat' => 40.7128, 'lon' => -74.0060],
            'distance' => '10km',
        ], $filters[0]);
    }

    /** @test */
    public function it_can_add_nested_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->nested('comments', function ($nestedBuilder) {
            $nestedBuilder->term('author', 'john');
        });

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals('nested', $filters[0]['type']);
        $this->assertEquals('comments', $filters[0]['path']);
        $this->assertArrayHasKey('query', $filters[0]);
    }

    /** @test */
    public function it_can_add_bool_must_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->must(function ($mustBuilder) {
            $mustBuilder->term('status', 'published');
            $mustBuilder->term('featured', true);
        });

        $filters = $builder->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals('bool', $filters[0]['type']);
        $this->assertArrayHasKey('bool', $filters[0]);
        $this->assertArrayHasKey('must', $filters[0]['bool']);
    }

    /** @test */
    public function it_can_build_single_filter(): void
    {
        $builder = new FilterBuilder();
        $builder->term('status', 'published');

        $built = $builder->build();

        $this->assertEquals([
            'type' => 'term',
            'field' => 'status',
            'value' => 'published',
        ], $built);
    }

    /** @test */
    public function it_can_build_multiple_filters(): void
    {
        $builder = new FilterBuilder();
        $builder->term('status', 'published');
        $builder->range('price', ['gte' => 100]);

        $built = $builder->build();

        $this->assertEquals([
            'type' => 'bool',
            'bool' => [
                'must' => [
                    [
                        'type' => 'term',
                        'field' => 'status',
                        'value' => 'published',
                    ],
                    [
                        'type' => 'range',
                        'field' => 'price',
                        'range' => ['gte' => 100],
                    ],
                ],
            ],
        ], $built);
    }

    /** @test */
    public function it_can_check_if_has_filters(): void
    {
        $builder = new FilterBuilder();

        $this->assertFalse($builder->hasFilters());

        $builder->term('status', 'published');

        $this->assertTrue($builder->hasFilters());
    }

    /** @test */
    public function it_can_clear_filters(): void
    {
        $builder = new FilterBuilder();
        $builder->term('status', 'published');

        $this->assertTrue($builder->hasFilters());

        $builder->clear();

        $this->assertFalse($builder->hasFilters());
        $this->assertEquals([], $builder->build());
    }

    /** @test */
    public function it_returns_empty_array_when_no_filters(): void
    {
        $builder = new FilterBuilder();

        $this->assertEquals([], $builder->build());
    }
}
