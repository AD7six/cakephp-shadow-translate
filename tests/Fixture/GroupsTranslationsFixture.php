<?php
namespace ShadowTranslate\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Class GroupsTranslationsFixture
 *
 */
class GroupsTranslationsFixture extends TestFixture
{
    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'locale' => ['type' => 'string', 'null' => false],
        'title' => ['type' => 'string'],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id', 'locale']]],
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [];
}
