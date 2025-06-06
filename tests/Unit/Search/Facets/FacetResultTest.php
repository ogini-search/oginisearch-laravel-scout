<?php

namespace OginiScoutDriver\Tests\Unit\Search\Facets;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Search\Facets\FacetResult;

class FacetResultTest extends TestCase
{
    public function testCanCreateFacetResult(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
            ['key' => 'Clothing', 'count' => 10],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $this->assertEquals('category', $facetResult->getField());
        $this->assertEquals('terms', $facetResult->getType());
        $this->assertCount(3, $facetResult->getBuckets());
    }

    public function testCanGetBuckets(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
            ['key' => 'Clothing', 'count' => 10],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $this->assertEquals($buckets, $facetResult->getBuckets());
    }

    public function testCanGetBucketCount(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $this->assertEquals(25, $facetResult->getBucketCount('Electronics'));
        $this->assertEquals(15, $facetResult->getBucketCount('Books'));
        $this->assertEquals(0, $facetResult->getBucketCount('NonExistent'));
    }

    public function testCanCheckIfBucketExists(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $this->assertTrue($facetResult->hasBucket('Electronics'));
        $this->assertTrue($facetResult->hasBucket('Books'));
        $this->assertFalse($facetResult->hasBucket('NonExistent'));
    }

    public function testCanGetTotal(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
            ['key' => 'Clothing', 'count' => 10],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $this->assertEquals(50, $facetResult->getTotal());
    }

    public function testCanGetEmptyBuckets(): void
    {
        $facetResult = new FacetResult('category', 'terms', []);

        $this->assertEmpty($facetResult->getBuckets());
        $this->assertEquals(0, $facetResult->getTotal());
        $this->assertFalse($facetResult->hasBucket('Electronics'));
        $this->assertTrue($facetResult->isEmpty());
    }

    public function testCanGetTopBuckets(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
            ['key' => 'Clothing', 'count' => 10],
            ['key' => 'Sports', 'count' => 5],
            ['key' => 'Music', 'count' => 3],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $topThree = $facetResult->topBuckets(3);
        $this->assertCount(3, $topThree);
        $this->assertEquals('Electronics', $topThree->first()['key']);
        $this->assertEquals('Books', $topThree->skip(1)->first()['key']);
        $this->assertEquals('Clothing', $topThree->skip(2)->first()['key']);
    }

    public function testCanSortBuckets(): void
    {
        $buckets = [
            ['key' => 'Books', 'count' => 15],
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Clothing', 'count' => 10],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $sortedByCount = $facetResult->sortedByCount();
        $keys = $sortedByCount->pluck('key')->toArray();
        $this->assertEquals(['Electronics', 'Books', 'Clothing'], $keys);

        $sortedByKey = $facetResult->sortedByKey();
        $keys = $sortedByKey->pluck('key')->toArray();
        $this->assertEquals(['Books', 'Clothing', 'Electronics'], $keys);
    }

    public function testCanFilterNonEmptyBuckets(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 0],
            ['key' => 'Clothing', 'count' => 10],
            ['key' => 'Sports', 'count' => 0],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $nonEmpty = $facetResult->nonEmptyBuckets();
        $this->assertCount(2, $nonEmpty);
        $this->assertTrue($nonEmpty->contains('key', 'Electronics'));
        $this->assertTrue($nonEmpty->contains('key', 'Clothing'));
    }

    public function testCanConvertToArray(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);

        $array = $facetResult->toArray();

        $this->assertArrayHasKey('field', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('buckets', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertEquals('category', $array['field']);
        $this->assertEquals('terms', $array['type']);
        $this->assertEquals($buckets, $array['buckets']);
        $this->assertEquals(40, $array['total']);
    }

    public function testCanCreateRangeFacetResult(): void
    {
        $ranges = [
            ['key' => '0-100', 'count' => 15],
            ['key' => '100-500', 'count' => 25],
            ['key' => '500-1000', 'count' => 10],
        ];

        $facetResult = new FacetResult('price', 'range', $ranges);

        $this->assertEquals('price', $facetResult->getField());
        $this->assertEquals('range', $facetResult->getType());
        $this->assertEquals(50, $facetResult->getTotal());
    }

    public function testCanCreateDateHistogramFacetResult(): void
    {
        $histogram = [
            ['key' => '2023-01-01', 'count' => 20],
            ['key' => '2023-01-02', 'count' => 15],
            ['key' => '2023-01-03', 'count' => 25],
        ];

        $facetResult = new FacetResult('created_at', 'date_histogram', $histogram);

        $this->assertEquals('created_at', $facetResult->getField());
        $this->assertEquals('date_histogram', $facetResult->getType());
        $this->assertEquals(60, $facetResult->getTotal());
    }

    public function testCanCreateFromResponse(): void
    {
        $responseData = [
            'type' => 'terms',
            'buckets' => [
                ['key' => 'Electronics', 'count' => 25],
                ['key' => 'Books', 'count' => 15],
                ['key' => 'Clothing', 'count' => 10],
            ],
        ];

        $facetResult = FacetResult::fromResponse('category', $responseData);

        $this->assertEquals('category', $facetResult->getField());
        $this->assertEquals('terms', $facetResult->getType());
        $this->assertEquals(25, $facetResult->getBucketCount('Electronics'));
        $this->assertEquals(15, $facetResult->getBucketCount('Books'));
        $this->assertEquals(10, $facetResult->getBucketCount('Clothing'));
    }

    public function testCanGetKeys(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);
        $keys = $facetResult->getKeys();

        $this->assertCount(2, $keys);
        $this->assertTrue($keys->contains('Electronics'));
        $this->assertTrue($keys->contains('Books'));
    }

    public function testCanGetCounts(): void
    {
        $buckets = [
            ['key' => 'Electronics', 'count' => 25],
            ['key' => 'Books', 'count' => 15],
        ];

        $facetResult = new FacetResult('category', 'terms', $buckets);
        $counts = $facetResult->getCounts();

        $this->assertCount(2, $counts);
        $this->assertTrue($counts->contains(25));
        $this->assertTrue($counts->contains(15));
    }
}
