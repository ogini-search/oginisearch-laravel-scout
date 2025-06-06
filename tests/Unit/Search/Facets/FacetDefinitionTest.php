<?php

namespace OginiScoutDriver\Tests\Unit\Search\Facets;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Search\Facets\FacetDefinition;

class FacetDefinitionTest extends TestCase
{
    /** @test */
    public function it_can_create_a_terms_facet(): void
    {
        $facet = FacetDefinition::terms('category', 15);

        $this->assertEquals('category', $facet->getField());
        $this->assertEquals(FacetDefinition::TYPE_TERMS, $facet->getType());
        $this->assertEquals(15, $facet->getOption('size'));
    }

    /** @test */
    public function it_can_create_a_range_facet(): void
    {
        $ranges = [
            ['from' => 0, 'to' => 100],
            ['from' => 100, 'to' => 500],
            ['from' => 500],
        ];

        $facet = FacetDefinition::range('price', $ranges);

        $this->assertEquals('price', $facet->getField());
        $this->assertEquals(FacetDefinition::TYPE_RANGE, $facet->getType());
        $this->assertEquals($ranges, $facet->getOption('ranges'));
    }

    /** @test */
    public function it_can_create_a_date_histogram_facet(): void
    {
        $facet = FacetDefinition::dateHistogram('created_at', '1M');

        $this->assertEquals('created_at', $facet->getField());
        $this->assertEquals(FacetDefinition::TYPE_DATE_HISTOGRAM, $facet->getType());
        $this->assertEquals('1M', $facet->getOption('interval'));
    }

    /** @test */
    public function it_can_create_a_histogram_facet(): void
    {
        $facet = FacetDefinition::histogram('price', 50.0);

        $this->assertEquals('price', $facet->getField());
        $this->assertEquals(FacetDefinition::TYPE_HISTOGRAM, $facet->getType());
        $this->assertEquals(50.0, $facet->getOption('interval'));
    }

    /** @test */
    public function it_can_set_and_get_options(): void
    {
        $facet = new FacetDefinition('test_field');

        $facet->setOption('custom_option', 'custom_value');

        $this->assertEquals('custom_value', $facet->getOption('custom_option'));
        $this->assertNull($facet->getOption('non_existent'));
        $this->assertEquals('default', $facet->getOption('non_existent', 'default'));
    }

    /** @test */
    public function it_can_convert_to_array(): void
    {
        $facet = FacetDefinition::terms('category', 10, ['min_doc_count' => 1]);

        $array = $facet->toArray();

        $this->assertEquals([
            'field' => 'category',
            'type' => 'terms',
            'size' => 10,
            'min_doc_count' => 1,
        ], $array);
    }

    /** @test */
    public function it_can_convert_to_json(): void
    {
        $facet = FacetDefinition::terms('category', 5);

        $json = $facet->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals([
            'field' => 'category',
            'type' => 'terms',
            'size' => 5,
        ], $decoded);
    }

    /** @test */
    public function it_has_correct_default_values(): void
    {
        $facet = new FacetDefinition('test_field');

        $this->assertEquals('test_field', $facet->getField());
        $this->assertEquals(FacetDefinition::TYPE_TERMS, $facet->getType());
        $this->assertEquals([], $facet->getOptions());
    }

    /** @test */
    public function it_can_merge_options_in_static_methods(): void
    {
        $facet = FacetDefinition::terms('category', 10, ['min_doc_count' => 2]);

        $this->assertEquals(10, $facet->getOption('size'));
        $this->assertEquals(2, $facet->getOption('min_doc_count'));
    }
}
