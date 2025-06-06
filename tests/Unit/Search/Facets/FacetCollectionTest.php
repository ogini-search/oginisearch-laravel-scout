<?php

namespace OginiScoutDriver\Tests\Unit\Search\Facets;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Search\Facets\FacetCollection;
use OginiScoutDriver\Search\Facets\FacetResult;

class FacetCollectionTest extends TestCase
{
    public function testCanCreateEmptyCollection(): void
    {
        $collection = new FacetCollection();

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
        $this->assertEmpty($collection->toArray());
    }

    public function testCanAddFacetResult(): void
    {
        $collection = new FacetCollection();
        $facetResult = new FacetResult('category', 'terms', [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
        ]);

        $collection->add('category', $facetResult);

        $this->assertCount(1, $collection);
        $this->assertFalse($collection->isEmpty());
        $this->assertTrue($collection->has('category'));
        $this->assertSame($facetResult, $collection->get('category'));
    }

    public function testCanAddMultipleFacetResults(): void
    {
        $collection = new FacetCollection();

        $categoryFacet = new FacetResult('category', 'terms', [
            ['key' => 'Electronics', 'count' => 25],
        ]);

        $priceFacet = new FacetResult('price', 'range', [
            ['key' => '0-100', 'count' => 15],
        ]);

        $collection->add('category', $categoryFacet);
        $collection->add('price', $priceFacet);

        $this->assertCount(2, $collection);
        $this->assertTrue($collection->has('category'));
        $this->assertTrue($collection->has('price'));
        $this->assertSame($categoryFacet, $collection->get('category'));
        $this->assertSame($priceFacet, $collection->get('price'));
    }

    public function testCanRemoveFacetResult(): void
    {
        $collection = new FacetCollection();
        $facetResult = new FacetResult('category', 'terms', []);

        $collection->add('category', $facetResult);
        $this->assertTrue($collection->has('category'));

        $collection->remove('category');
        $this->assertFalse($collection->has('category'));
        $this->assertCount(0, $collection);
    }

    public function testCanGetNonExistentFacet(): void
    {
        $collection = new FacetCollection();

        $this->assertNull($collection->get('nonexistent'));
        $this->assertFalse($collection->has('nonexistent'));
    }

    public function testCanGetAllFacetFields(): void
    {
        $collection = new FacetCollection();

        $collection->add('category', new FacetResult('category', 'terms', []));
        $collection->add('price', new FacetResult('price', 'range', []));
        $collection->add('brand', new FacetResult('brand', 'terms', []));

        $fields = $collection->fields();
        $this->assertCount(3, $fields);
        $this->assertContains('category', $fields);
        $this->assertContains('price', $fields);
        $this->assertContains('brand', $fields);
    }

    public function testCanGetAllFacetResults(): void
    {
        $collection = new FacetCollection();

        $categoryFacet = new FacetResult('category', 'terms', []);
        $priceFacet = new FacetResult('price', 'range', []);

        $collection->add('category', $categoryFacet);
        $collection->add('price', $priceFacet);

        $results = $collection->all();
        $this->assertCount(2, $results);
        $this->assertArrayHasKey('category', $results);
        $this->assertArrayHasKey('price', $results);
        $this->assertSame($categoryFacet, $results['category']);
        $this->assertSame($priceFacet, $results['price']);
    }

    public function testCanIterateOverCollection(): void
    {
        $collection = new FacetCollection();

        $categoryFacet = new FacetResult('category', 'terms', []);
        $priceFacet = new FacetResult('price', 'range', []);

        $collection->add('category', $categoryFacet);
        $collection->add('price', $priceFacet);

        $iteratedResults = [];
        foreach ($collection as $name => $facet) {
            $iteratedResults[$name] = $facet;
        }

        $this->assertCount(2, $iteratedResults);
        $this->assertArrayHasKey('category', $iteratedResults);
        $this->assertArrayHasKey('price', $iteratedResults);
        $this->assertSame($categoryFacet, $iteratedResults['category']);
        $this->assertSame($priceFacet, $iteratedResults['price']);
    }

    public function testCanRemoveAllFacets(): void
    {
        $collection = new FacetCollection();

        $collection->add('category', new FacetResult('category', 'terms', []));
        $collection->add('price', new FacetResult('price', 'range', []));

        $this->assertCount(2, $collection);

        $collection->remove('category');
        $collection->remove('price');

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    public function testCanConvertToArray(): void
    {
        $collection = new FacetCollection();

        $categoryBuckets = [['key' => 'Electronics', 'count' => 25]];
        $priceBuckets = [['key' => '0-100', 'count' => 15]];

        $collection->add('category', new FacetResult('category', 'terms', $categoryBuckets));
        $collection->add('price', new FacetResult('price', 'range', $priceBuckets));

        $array = $collection->toArray();

        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('price', $array);
        $this->assertEquals('terms', $array['category']['type']);
        $this->assertEquals('range', $array['price']['type']);
        $this->assertEquals($categoryBuckets, $array['category']['buckets']);
        $this->assertEquals($priceBuckets, $array['price']['buckets']);
    }

    public function testCanConvertToJson(): void
    {
        $collection = new FacetCollection();

        $collection->add('category', new FacetResult('category', 'terms', [
            ['key' => 'Electronics', 'count' => 25]
        ]));

        $json = $collection->toJson();
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('category', $decoded);
        $this->assertEquals('category', $decoded['category']['field']);
        $this->assertEquals('terms', $decoded['category']['type']);
    }

    public function testCanCreateFromMultipleFacetResults(): void
    {
        $facets = [
            'category' => new FacetResult('category', 'terms', []),
            'price' => new FacetResult('price', 'range', []),
        ];

        $collection = new FacetCollection($facets);

        $this->assertCount(2, $collection);
        $this->assertTrue($collection->has('category'));
        $this->assertTrue($collection->has('price'));
    }

    public function testCanConvertToCollection(): void
    {
        $collection = new FacetCollection();

        $collection->add('category', new FacetResult('category', 'terms', [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
        ]));

        $collection->add('price', new FacetResult('price', 'range', [
            ['key' => '0-100', 'count' => 10],
        ]));

        $laravelCollection = $collection->collect();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $laravelCollection);
        $this->assertCount(2, $laravelCollection);
        $this->assertTrue($laravelCollection->has('category'));
        $this->assertTrue($laravelCollection->has('price'));
    }

    public function testCanFilterFacets(): void
    {
        $collection = new FacetCollection();

        $collection->add('category', new FacetResult('category', 'terms', [
            ['key' => 'Electronics', 'count' => 25],
        ]));

        $collection->add('price', new FacetResult('price', 'range', [
            ['key' => '0-100', 'count' => 10],
        ]));

        $collection->add('empty', new FacetResult('empty', 'terms', []));

        $filtered = $collection->filter(function ($facet) {
            return !$facet->isEmpty();
        });

        $this->assertCount(2, $filtered);
        $this->assertTrue($filtered->has('category'));
        $this->assertTrue($filtered->has('price'));
        $this->assertFalse($filtered->has('empty'));
    }
}
