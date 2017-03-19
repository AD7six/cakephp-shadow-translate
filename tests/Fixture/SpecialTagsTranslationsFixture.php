<?php
namespace ShadowTranslate\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Class SpecialTagsTranslationsFixture
 */
class SpecialTagsTranslationsFixture extends TestFixture
{

    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'locale' => ['type' => 'string', 'null' => false],
        'extra_info' => ['type' => 'string'],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id', 'locale']]],
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['id' => 2, 'locale' => 'eng', 'extra_info' => 'Translated Info'],
    ];
}
