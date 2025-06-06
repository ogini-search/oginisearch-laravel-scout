<?php

namespace OginiScoutDriver\Tests\Unit\Search\Highlighting;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Search\Highlighting\HighlightBuilder;

class HighlightBuilderTest extends TestCase
{
    public function testCanCreateEmptyHighlightBuilder(): void
    {
        $builder = new HighlightBuilder();

        $this->assertEmpty($builder->getFields());
        $this->assertEmpty($builder->getGlobalOptions());
        $this->assertFalse($builder->hasFields());
    }

    public function testCanAddSingleField(): void
    {
        $builder = new HighlightBuilder();
        $result = $builder->field('title');

        $this->assertSame($builder, $result);
        $this->assertTrue($builder->hasFields());
        $this->assertArrayHasKey('title', $builder->getFields());
        $this->assertEmpty($builder->getFields()['title']);
    }

    public function testCanAddFieldWithOptions(): void
    {
        $builder = new HighlightBuilder();
        $options = ['fragment_size' => 100, 'number_of_fragments' => 3];
        $builder->field('content', $options);

        $fields = $builder->getFields();
        $this->assertEquals($options, $fields['content']);
    }

    public function testCanAddMultipleFields(): void
    {
        $builder = new HighlightBuilder();
        $fieldsArray = [
            'title' => ['fragment_size' => 50],
            'content' => ['fragment_size' => 150, 'number_of_fragments' => 5],
            'description',
        ];

        $builder->fields($fieldsArray);

        $fields = $builder->getFields();
        $this->assertArrayHasKey('title', $fields);
        $this->assertArrayHasKey('content', $fields);
        $this->assertArrayHasKey('description', $fields);
        $this->assertEquals(['fragment_size' => 50], $fields['title']);
        $this->assertEquals(['fragment_size' => 150, 'number_of_fragments' => 5], $fields['content']);
        $this->assertEmpty($fields['description']);
    }

    public function testCanSetPreTag(): void
    {
        $builder = new HighlightBuilder();
        $result = $builder->preTag('<mark>');

        $this->assertSame($builder, $result);
        $this->assertEquals('<mark>', $builder->getGlobalOptions()['pre_tag']);
    }

    public function testCanSetPostTag(): void
    {
        $builder = new HighlightBuilder();
        $builder->postTag('</mark>');

        $this->assertEquals('</mark>', $builder->getGlobalOptions()['post_tag']);
    }

    public function testCanSetBothTags(): void
    {
        $builder = new HighlightBuilder();
        $builder->tags('<strong>', '</strong>');

        $options = $builder->getGlobalOptions();
        $this->assertEquals('<strong>', $options['pre_tag']);
        $this->assertEquals('</strong>', $options['post_tag']);
    }

    public function testCanSetHtmlTag(): void
    {
        $builder = new HighlightBuilder();
        $builder->htmlTag('em');

        $options = $builder->getGlobalOptions();
        $this->assertEquals('<em>', $options['pre_tag']);
        $this->assertEquals('</em>', $options['post_tag']);
    }

    public function testCanSetHtmlTagWithAttributes(): void
    {
        $builder = new HighlightBuilder();
        $builder->htmlTag('span', ['class' => 'highlight', 'style' => 'background: yellow']);

        $options = $builder->getGlobalOptions();
        $this->assertEquals('<span class="highlight" style="background: yellow">', $options['pre_tag']);
        $this->assertEquals('</span>', $options['post_tag']);
    }

    public function testCanSetFragmentSize(): void
    {
        $builder = new HighlightBuilder();
        $builder->fragmentSize(200);

        $this->assertEquals(200, $builder->getGlobalOptions()['fragment_size']);
    }

    public function testCanSetNumberOfFragments(): void
    {
        $builder = new HighlightBuilder();
        $builder->numberOfFragments(5);

        $this->assertEquals(5, $builder->getGlobalOptions()['number_of_fragments']);
    }

    public function testCanSetFragmentOffset(): void
    {
        $builder = new HighlightBuilder();
        $builder->fragmentOffset(10);

        $this->assertEquals(10, $builder->getGlobalOptions()['fragment_offset']);
    }

    public function testCanSetHighlighterType(): void
    {
        $builder = new HighlightBuilder();
        $builder->type('unified');

        $this->assertEquals('unified', $builder->getGlobalOptions()['type']);
    }

    public function testCanSetPlainHighlighter(): void
    {
        $builder = new HighlightBuilder();
        $builder->plain();

        $this->assertEquals('plain', $builder->getGlobalOptions()['type']);
    }

    public function testCanSetUnifiedHighlighter(): void
    {
        $builder = new HighlightBuilder();
        $builder->unified();

        $this->assertEquals('unified', $builder->getGlobalOptions()['type']);
    }

    public function testCanSetFastVectorHighlighter(): void
    {
        $builder = new HighlightBuilder();
        $builder->fastVector();

        $this->assertEquals('fvh', $builder->getGlobalOptions()['type']);
    }

    public function testCanSetBoundaryScanner(): void
    {
        $builder = new HighlightBuilder();
        $builder->boundaryScanner('sentence');

        $this->assertEquals('sentence', $builder->getGlobalOptions()['boundary_scanner']);
    }

    public function testCanSetBoundaryChars(): void
    {
        $builder = new HighlightBuilder();
        $builder->boundaryChars('.,!?;');

        $this->assertEquals('.,!?;', $builder->getGlobalOptions()['boundary_chars']);
    }

    public function testCanSetBoundaryMaxScan(): void
    {
        $builder = new HighlightBuilder();
        $builder->boundaryMaxScan(20);

        $this->assertEquals(20, $builder->getGlobalOptions()['boundary_max_scan']);
    }

    public function testCanSetMatchedFieldsOnly(): void
    {
        $builder = new HighlightBuilder();
        $builder->matchedFieldsOnly(true);

        $this->assertTrue($builder->getGlobalOptions()['matched_fields_only']);

        $builder->matchedFieldsOnly(false);
        $this->assertFalse($builder->getGlobalOptions()['matched_fields_only']);
    }

    public function testCanSetOrder(): void
    {
        $builder = new HighlightBuilder();
        $builder->order('score');

        $this->assertEquals('score', $builder->getGlobalOptions()['order']);
    }

    public function testCanOrderByScore(): void
    {
        $builder = new HighlightBuilder();
        $builder->orderByScore();

        $this->assertEquals('score', $builder->getGlobalOptions()['order']);
    }

    public function testCanOrderByPosition(): void
    {
        $builder = new HighlightBuilder();
        $builder->orderByPosition();

        $this->assertEquals('position', $builder->getGlobalOptions()['order']);
    }

    public function testCanSetPhraseLimit(): void
    {
        $builder = new HighlightBuilder();
        $builder->phraseLimit(256);

        $this->assertEquals(256, $builder->getGlobalOptions()['phrase_limit']);
    }

    public function testCanSetRequireFieldMatch(): void
    {
        $builder = new HighlightBuilder();
        $builder->requireFieldMatch(true);

        $this->assertTrue($builder->getGlobalOptions()['require_field_match']);

        $builder->requireFieldMatch(false);
        $this->assertFalse($builder->getGlobalOptions()['require_field_match']);
    }

    public function testCanSetNoMatchSize(): void
    {
        $builder = new HighlightBuilder();
        $builder->noMatchSize(100);

        $this->assertEquals(100, $builder->getGlobalOptions()['no_match_size']);
    }

    public function testCanAddFieldWithOptionsMethod(): void
    {
        $builder = new HighlightBuilder();
        $builder->fieldWithOptions('content', 200, 5, ['type' => 'unified']);

        $fields = $builder->getFields();
        $this->assertArrayHasKey('content', $fields);
        $this->assertEquals(200, $fields['content']['fragment_size']);
        $this->assertEquals(5, $fields['content']['number_of_fragments']);
        $this->assertEquals('unified', $fields['content']['type']);
    }

    public function testCanBuildHighlightQuery(): void
    {
        $builder = new HighlightBuilder();
        $builder->field('title')
            ->field('content', ['fragment_size' => 150])
            ->preTag('<em>')
            ->postTag('</em>')
            ->fragmentSize(100)
            ->numberOfFragments(3);

        $built = $builder->build();

        $this->assertArrayHasKey('fields', $built);
        $this->assertArrayHasKey('pre_tag', $built);
        $this->assertArrayHasKey('post_tag', $built);
        $this->assertArrayHasKey('fragment_size', $built);
        $this->assertArrayHasKey('number_of_fragments', $built);

        $this->assertEquals('<em>', $built['pre_tag']);
        $this->assertEquals('</em>', $built['post_tag']);
        $this->assertEquals(100, $built['fragment_size']);
        $this->assertEquals(3, $built['number_of_fragments']);

        $this->assertArrayHasKey('title', $built['fields']);
        $this->assertArrayHasKey('content', $built['fields']);
        $this->assertEquals(150, $built['fields']['content']['fragment_size']);
    }

    public function testCanConvertToArray(): void
    {
        $builder = new HighlightBuilder();
        $builder->field('title')->preTag('<mark>');

        $array = $builder->toArray();
        $built = $builder->build();

        $this->assertEquals($built, $array);
    }

    public function testCanClearFields(): void
    {
        $builder = new HighlightBuilder();
        $builder->field('title')->field('content');

        $this->assertTrue($builder->hasFields());

        $builder->clear();

        $this->assertFalse($builder->hasFields());
        $this->assertEmpty($builder->getFields());
        $this->assertEmpty($builder->getGlobalOptions());
    }

    public function testCanCreateSimpleHighlighter(): void
    {
        $fields = ['title', 'content'];
        $builder = HighlightBuilder::simple($fields, '<strong>', '</strong>');

        $this->assertTrue($builder->hasFields());
        $this->assertArrayHasKey('title', $builder->getFields());
        $this->assertArrayHasKey('content', $builder->getFields());

        $options = $builder->getGlobalOptions();
        $this->assertEquals('<strong>', $options['pre_tag']);
        $this->assertEquals('</strong>', $options['post_tag']);
    }

    public function testCanCreateHtmlHighlighter(): void
    {
        $fields = ['title', 'content'];
        $builder = HighlightBuilder::html($fields, 'mark', ['class' => 'highlight']);

        $this->assertTrue($builder->hasFields());
        $this->assertArrayHasKey('title', $builder->getFields());
        $this->assertArrayHasKey('content', $builder->getFields());

        $options = $builder->getGlobalOptions();
        $this->assertEquals('<mark class="highlight">', $options['pre_tag']);
        $this->assertEquals('</mark>', $options['post_tag']);
    }

    public function testCanChainMultipleOperations(): void
    {
        $builder = new HighlightBuilder();
        $result = $builder->field('title')
            ->field('content')
            ->htmlTag('em')
            ->fragmentSize(150)
            ->numberOfFragments(3)
            ->unified()
            ->orderByScore()
            ->requireFieldMatch(true);

        $this->assertSame($builder, $result);
        $this->assertCount(2, $builder->getFields());

        $options = $builder->getGlobalOptions();
        $this->assertEquals('<em>', $options['pre_tag']);
        $this->assertEquals('</em>', $options['post_tag']);
        $this->assertEquals(150, $options['fragment_size']);
        $this->assertEquals(3, $options['number_of_fragments']);
        $this->assertEquals('unified', $options['type']);
        $this->assertEquals('score', $options['order']);
        $this->assertTrue($options['require_field_match']);
    }
}
