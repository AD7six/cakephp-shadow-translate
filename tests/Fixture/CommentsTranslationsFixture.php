<?php
namespace ShadowTranslate\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Class CommentsTranslationsFixture
 *
 */
class CommentsTranslationsFixture extends TestFixture {

/**
 * fields property
 *
 * @var array
 */
	public $fields = [
		'id' => ['type' => 'integer'],
		'locale' => ['type' => 'string', 'null' => false],
		'comment' => 'text',
		'_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id', 'locale']]]
	];

/**
 * records property
 *
 * @var array
 */
	public $records = [
		['locale' => 'eng', 'id' => 1, 'comment' => 'Comment #1'],
		['locale' => 'eng', 'id' => 2, 'comment' => 'Comment #2'],
		['locale' => 'eng', 'id' => 3, 'comment' => 'Comment #3'],
		['locale' => 'eng', 'id' => 4, 'comment' => 'Comment #4'],
		['locale' => 'spa', 'id' => 4, 'comment' => 'Comentario #4'],
	];
}
