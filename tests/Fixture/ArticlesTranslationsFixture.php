<?php
namespace ShadowTranslate\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Class ArticlesTranslationsFixture
 *
 */
class ArticlesTranslationsFixture extends TestFixture
{
    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'locale' => ['type' => 'string', 'null' => false],
        'title' => ['type' => 'string', 'null' => true],
        'body' => 'text',
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id', 'locale']]],
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['locale' => 'eng', 'id' => 1, 'title' => 'Title #1', 'body' => 'Content #1'],
        ['locale' => 'deu', 'id' => 1, 'title' => 'Titel #1', 'body' => 'Inhalt #1'],
        ['locale' => 'cze', 'id' => 1, 'title' => 'Titulek #1', 'body' => 'Obsah #1'],
        ['locale' => 'spa', 'id' => 1, 'title' => 'First Article', 'body' => 'Contenido #1'],
        ['locale' => 'zzz', 'id' => 1, 'title' => '', 'body' => ''],
        ['locale' => 'eng', 'id' => 2, 'title' => 'Title #2', 'body' => 'Content #2'],
        ['locale' => 'deu', 'id' => 2, 'title' => 'Titel #2', 'body' => 'Inhalt #2'],
        ['locale' => 'cze', 'id' => 2, 'title' => 'Titulek #2', 'body' => 'Obsah #2'],
        ['locale' => 'eng', 'id' => 3, 'title' => 'Title #3', 'body' => 'Content #3'],
        ['locale' => 'deu', 'id' => 3, 'title' => 'Titel #3', 'body' => 'Inhalt #3'],
        ['locale' => 'cze', 'id' => 3, 'title' => 'Titulek #3', 'body' => 'Obsah #3'],
    ];
}
