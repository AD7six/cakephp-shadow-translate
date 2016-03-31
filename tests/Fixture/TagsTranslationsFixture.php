<?php
namespace ShadowTranslate\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Class TagsTranslationsFixture
 *
 */
class TagsTranslationsFixture extends TestFixture
{
    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'locale' => ['type' => 'string', 'null' => false],
        'name' => ['type' => 'string', 'null' => false],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id', 'locale']]],
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['locale' => 'eng', 'id' => 1, 'name' => 'tag1 in eng'],
        ['locale' => 'deu', 'id' => 1, 'name' => 'tag1 in deu'],
        ['locale' => 'cze', 'id' => 1, 'name' => 'tag1 in cze'],
        ['locale' => 'eng', 'id' => 2, 'name' => 'tag2 in eng'],
        ['locale' => 'deu', 'id' => 2, 'name' => 'tag2 in deu'],
        ['locale' => 'cze', 'id' => 2, 'name' => 'tag2 in cze'],
        ['locale' => 'eng', 'id' => 3, 'name' => 'tag3 in eng'],
        ['locale' => 'deu', 'id' => 3, 'name' => 'tag3 in deu'],
        ['locale' => 'cze', 'id' => 3, 'name' => 'tag3 in cze'],
    ];
}
