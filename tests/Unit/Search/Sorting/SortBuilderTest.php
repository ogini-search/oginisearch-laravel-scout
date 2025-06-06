<?php

namespace OginiScoutDriver\Tests\Unit\Search\Sorting;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Search\Sorting\SortBuilder;

class SortBuilderTest extends TestCase
{
    public function testCanCreateEmptySortBuilder(): void
    {
        $builder = new SortBuilder();

        $this->assertCount(0, $builder->getSorts());
        $this->assertFalse($builder->hasSorts());
        $this->assertEmpty($builder->build());
    }

    public function testCanAddFieldSort(): void
    {
        $builder = new SortBuilder();
        $result = $builder->field('title', 'asc');

        $this->assertSame($builder, $result);
        $this->assertTrue($builder->hasSorts());
        $this->assertCount(1, $builder->getSorts());

        $sorts = $builder->getSorts();
        $this->assertEquals('title', $sorts[0]['field']);
        $this->assertEquals('asc', $sorts[0]['direction']);
    }

    public function testCanAddAscendingSort(): void
    {
        $builder = new SortBuilder();
        $builder->asc('price');

        $sorts = $builder->getSorts();
        $this->assertEquals('price', $sorts[0]['field']);
        $this->assertEquals('asc', $sorts[0]['direction']);
    }

    public function testCanAddDescendingSort(): void
    {
        $builder = new SortBuilder();
        $builder->desc('created_at');

        $sorts = $builder->getSorts();
        $this->assertEquals('created_at', $sorts[0]['field']);
        $this->assertEquals('desc', $sorts[0]['direction']);
    }

    public function testCanAddScoreSort(): void
    {
        $builder = new SortBuilder();
        $builder->score('desc');

        $sorts = $builder->getSorts();
        $this->assertEquals('_score', $sorts[0]['field']);
        $this->assertEquals('desc', $sorts[0]['direction']);
    }

    public function testCanAddMultipleSorts(): void
    {
        $builder = new SortBuilder();
        $builder->desc('_score')
            ->asc('title')
            ->desc('created_at');

        $this->assertCount(3, $builder->getSorts());

        $sorts = $builder->getSorts();
        $this->assertEquals('_score', $sorts[0]['field']);
        $this->assertEquals('desc', $sorts[0]['direction']);
        $this->assertEquals('title', $sorts[1]['field']);
        $this->assertEquals('asc', $sorts[1]['direction']);
        $this->assertEquals('created_at', $sorts[2]['field']);
        $this->assertEquals('desc', $sorts[2]['direction']);
    }

    public function testCanAddGeoDistanceSort(): void
    {
        $builder = new SortBuilder();
        $location = ['lat' => 40.7128, 'lon' => -74.0060];
        $builder->geoDistance('location', $location, 'asc');

        $sorts = $builder->getSorts();
        $this->assertEquals('geo_distance', $sorts[0]['type']);
        $this->assertEquals('location', $sorts[0]['field']);
        $this->assertEquals($location, $sorts[0]['location']);
        $this->assertEquals('asc', $sorts[0]['direction']);
    }

    public function testCanAddScriptSort(): void
    {
        $builder = new SortBuilder();
        $script = "Math.log(2 + doc['likes'].value)";
        $builder->script($script, 'desc');

        $sorts = $builder->getSorts();
        $this->assertEquals('script', $sorts[0]['type']);
        $this->assertEquals($script, $sorts[0]['script']);
        $this->assertEquals('desc', $sorts[0]['direction']);
    }

    public function testCanAddNestedSort(): void
    {
        $builder = new SortBuilder();
        $builder->nested('comments', 'score', 'desc');

        $sorts = $builder->getSorts();
        $this->assertEquals('nested', $sorts[0]['type']);
        $this->assertEquals('comments', $sorts[0]['path']);
        $this->assertEquals('score', $sorts[0]['field']);
        $this->assertEquals('desc', $sorts[0]['direction']);
    }

    public function testCanAddRandomSort(): void
    {
        $builder = new SortBuilder();
        $builder->random(12345);

        $sorts = $builder->getSorts();
        $this->assertEquals('random', $sorts[0]['type']);
        $this->assertEquals(12345, $sorts[0]['seed']);
    }

    public function testCanAddRandomSortWithoutSeed(): void
    {
        $builder = new SortBuilder();
        $builder->random();

        $sorts = $builder->getSorts();
        $this->assertEquals('random', $sorts[0]['type']);
        $this->assertArrayNotHasKey('seed', $sorts[0]);
    }

    public function testCanAddSortWithMissingValue(): void
    {
        $builder = new SortBuilder();
        $builder->withMissing('optional_field', 'asc', '_last');

        $sorts = $builder->getSorts();
        $this->assertEquals('optional_field', $sorts[0]['field']);
        $this->assertEquals('asc', $sorts[0]['direction']);
        $this->assertEquals('_last', $sorts[0]['missing']);
    }

    public function testCanAddSortWithMode(): void
    {
        $builder = new SortBuilder();
        $builder->withMode('tags', 'asc', 'min');

        $sorts = $builder->getSorts();
        $this->assertEquals('tags', $sorts[0]['field']);
        $this->assertEquals('asc', $sorts[0]['direction']);
        $this->assertEquals('min', $sorts[0]['mode']);
    }

    public function testCanAddMultipleSortsFromArray(): void
    {
        $builder = new SortBuilder();
        $sortsArray = [
            'title',
            ['field' => 'price', 'direction' => 'desc'],
            ['field' => 'created_at', 'direction' => 'asc', 'missing' => '_first'],
        ];

        $builder->multiple($sortsArray);

        $this->assertCount(3, $builder->getSorts());

        $sorts = $builder->getSorts();
        $this->assertEquals('title', $sorts[0]['field']);
        $this->assertEquals('asc', $sorts[0]['direction']);
        $this->assertEquals('price', $sorts[1]['field']);
        $this->assertEquals('desc', $sorts[1]['direction']);
        $this->assertEquals('created_at', $sorts[2]['field']);
        $this->assertEquals('asc', $sorts[2]['direction']);
        $this->assertEquals('_first', $sorts[2]['missing']);
    }

    public function testCanBuildSortArray(): void
    {
        $builder = new SortBuilder();
        $builder->asc('title')->desc('price');

        $built = $builder->build();

        $this->assertIsArray($built);
        $this->assertCount(2, $built);
        $this->assertEquals('title', $built[0]['field']);
        $this->assertEquals('asc', $built[0]['direction']);
        $this->assertEquals('price', $built[1]['field']);
        $this->assertEquals('desc', $built[1]['direction']);
    }

    public function testCanBuildSortString(): void
    {
        $builder = new SortBuilder();
        $builder->asc('title')->desc('price');

        $sortString = $builder->buildString();

        $this->assertIsString($sortString);
        $this->assertStringContainsString('title:asc', $sortString);
        $this->assertStringContainsString('price:desc', $sortString);
    }

    public function testCanConvertToArray(): void
    {
        $builder = new SortBuilder();
        $builder->asc('title')->desc('price');

        $array = $builder->toArray();
        $built = $builder->build();

        $this->assertEquals($built, $array);
    }

    public function testCanCountSorts(): void
    {
        $builder = new SortBuilder();

        $this->assertEquals(0, $builder->count());

        $builder->asc('title');
        $this->assertEquals(1, $builder->count());

        $builder->desc('price');
        $this->assertEquals(2, $builder->count());
    }

    public function testCanCreateFromScoutOrders(): void
    {
        $scoutOrders = [
            ['column' => 'title', 'direction' => 'asc'],
            ['column' => 'price', 'direction' => 'desc'],
        ];

        $builder = SortBuilder::fromScoutOrders($scoutOrders);

        $this->assertCount(2, $builder->getSorts());
        $sorts = $builder->getSorts();
        $this->assertEquals('title', $sorts[0]['field']);
        $this->assertEquals('asc', $sorts[0]['direction']);
        $this->assertEquals('price', $sorts[1]['field']);
        $this->assertEquals('desc', $sorts[1]['direction']);
    }

    public function testCanAddFieldSortWithOptions(): void
    {
        $builder = new SortBuilder();
        $options = ['missing' => '_last', 'mode' => 'min'];
        $builder->field('score', 'desc', $options);

        $sorts = $builder->getSorts();
        $this->assertEquals('score', $sorts[0]['field']);
        $this->assertEquals('desc', $sorts[0]['direction']);
        $this->assertEquals('_last', $sorts[0]['missing']);
        $this->assertEquals('min', $sorts[0]['mode']);
    }

    public function testCanAddGeoDistanceSortWithOptions(): void
    {
        $builder = new SortBuilder();
        $location = ['lat' => 40.7128, 'lon' => -74.0060];
        $options = ['unit' => 'km', 'mode' => 'min'];
        $builder->geoDistance('location', $location, 'asc', $options);

        $sorts = $builder->getSorts();
        $this->assertEquals('geo_distance', $sorts[0]['type']);
        $this->assertEquals('km', $sorts[0]['unit']);
        $this->assertEquals('min', $sorts[0]['mode']);
    }

    public function testCanAddScriptSortWithOptions(): void
    {
        $builder = new SortBuilder();
        $script = "Math.log(2 + doc['likes'].value)";
        $options = ['script_type' => 'number', 'order' => 'desc'];
        $builder->script($script, 'desc', $options);

        $sorts = $builder->getSorts();
        $this->assertEquals('script', $sorts[0]['type']);
        $this->assertEquals($script, $sorts[0]['script']);
        $this->assertEquals('number', $sorts[0]['script_type']);
        $this->assertEquals('desc', $sorts[0]['order']);
    }

    public function testCanAddNestedSortWithOptions(): void
    {
        $builder = new SortBuilder();
        $options = ['mode' => 'avg', 'missing' => '_last'];
        $builder->nested('reviews', 'rating', 'desc', $options);

        $sorts = $builder->getSorts();
        $this->assertEquals('nested', $sorts[0]['type']);
        $this->assertEquals('reviews', $sorts[0]['path']);
        $this->assertEquals('rating', $sorts[0]['field']);
        $this->assertEquals('desc', $sorts[0]['direction']);
        $this->assertEquals('avg', $sorts[0]['mode']);
        $this->assertEquals('_last', $sorts[0]['missing']);
    }
}
