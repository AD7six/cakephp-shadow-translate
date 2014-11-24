<?php
/**
 * CakePHP(tm) Tests <http://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://book.cakephp.org/2.0/en/development/testing.html CakePHP(tm) Tests
 * @since         1.2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace ShadowTranslate\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Class ShadowPostFixture
 *
 */
class ArticlesTranslationsFixture extends TestFixture {

/**
 * fields property
 *
 * @var array
 */
	public $fields = [
		'id' => ['type' => 'integer'],
		'locale' => ['type' => 'string', 'null' => false],
		'title' => ['type' => 'string', 'null' => false],
		'body' => 'text',
		'_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id', 'locale']]]
	];

/**
 * records property
 *
 * @var array
 */
	public $records = [
		['id' => 1, 'locale' => 'eng', 'title' => '#1 ENG', 'body' => '#1 ENG body'],
		['id' => 2, 'locale' => 'eng', 'title' => '#2 ENG', 'body' => '#2 ENG body'],
		['id' => 1, 'locale' => 'es', 'title' => '#1 ES', 'body' => '#1 ES body'],
		['id' => 1, 'locale' => 'de', 'title' => '#1 DE', 'body' => '#1 DE body'],
		['id' => 2, 'locale' => 'es', 'title' => '#2 ES', 'body' => '#2 ES body'],
	];
}
