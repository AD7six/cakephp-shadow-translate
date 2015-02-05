<?php
namespace ShadowTranslate\Test\TestCase\Model\Behavior;

use Cake\I18n\I18n;
use Cake\ORM\Table as CakeTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Test\TestCase\ORM\Behavior\TranslateBehaviorTest;

/**
 * Test Table class
 *
 * The sole purpose of this class is to hijack behavior loading to substitude
 * The translate behavior with the shadow translate behavior. This allows the
 * core test case to be used to verify as close as possible that the shadow
 * translate behavior is functionally equivalent to the core behavior.
 */
class Table extends CakeTable {

	public function addBehavior($name, array $options = []) {
		if ($name === 'Translate') {
			$name = 'ShadowTranslate.ShadowTranslate';
		}
		$this->_behaviors->load($name, $options);
	}

}

/**
 * ShadowTranslateBehavior test case
 */
class ShadowTranslateBehaviorTest extends TranslateBehaviorTest {

	public $fixtures = [
		'core.Articles',
		'core.Authors',
		'core.Comments',
		'plugin.ShadowTranslate.ArticlesTranslations',
		'plugin.ShadowTranslate.ArticlesMoreTranslations',
		'plugin.ShadowTranslate.AuthorsTranslations',
		'plugin.ShadowTranslate.CommentsTranslations'
	];

/**
 * Seed the table registry with this test case's Table class
 *
 * @return void
 */
	public function setUp() {
		$aliases = ['Articles', 'Authors', 'Comments'];
		$options = ['className' => 'ShadowTranslate\Test\TestCase\Model\Behavior\Table'];

		foreach ($aliases as $alias) {
			TableRegistry::get($alias, $options);
		}

		parent::setUp();
	}

/**
 * Allow usage without specifying fields explicitly
 *
 * @return void
 */
	public function testAutoFieldDetection() {
		$table = TableRegistry::get('Articles');
		$table->addBehavior('Translate');

		$table->locale('eng');
		$table->find()->first();

		$expected = ['title', 'body'];
		$result = $table->behaviors()->get('ShadowTranslate')->config('fields');
		$this->assertSame(
			$expected,
			$result,
			'If no fields are specified, they should be derived from the schema'
		);
	}

/**
 * testExcludeUntranslated
 *
 * @return void
 */
	public function testExcludeUntranslated() {
		$table = TableRegistry::get('Authors');
		$dbConfig = $table->connection()->config('driver');
		$usingSqlite = ($dbConfig['driver'] === 'Cake\Database\Driver\Sqlite');
		$this->skipIf($usingSqlite, 'Sqlite does not support right joins, on which this functionality depends');

		$table->addBehavior('Translate', ['joinType' => 'RIGHT']);
		$table->locale('eng');
		$results = $table->find('list')->toArray();
		$expected = [
			1 => 'May-rianoh'
		];
		$this->assertSame($expected, $results);
	}

/**
 * Verify it is not necessary for a translated field to exist in the master table
 *
 * @return void
 */
	public function testVirtualTranslationField() {
		$table = TableRegistry::get('Articles');
		$table->addBehavior('Translate', [
			'alias' => 'ArticlesMoreTranslations',
			'translationTable' => 'articles_more_translations'
		]);

		$table->locale('eng');
		$results = $table->find()->combine('title', 'subtitle', 'id')->toArray();
		$expected = [
			1 => ['Title #1' => 'SubTitle #1'],
			2 => ['Title #2' => 'SubTitle #2'],
			3 => ['Title #3' => 'SubTitle #3'],
		];
		$this->assertSame($expected, $results);
	}

/**
 * Tests that after deleting a translated entity, all translations are also removed
 *
 * @return void
 */
	public function testDelete() {
		$table = TableRegistry::get('Articles');
		$table->addBehavior('Translate', ['fields' => ['title', 'body']]);
		$article = $table->find()->first();
		$this->assertTrue($table->delete($article));

		$translations = TableRegistry::get('ArticlesTranslations')->find()
			->where(['id' => $article->id])
			->count();
		$this->assertEquals(0, $translations);
	}

/**
 * testNoAmbiguousFields
 *
 * @return void
 */
	public function testNoAmbiguousFields() {
		$table = TableRegistry::get('Articles');
		$table->addBehavior('Translate', ['fields' => ['title', 'body']]);
		$table->locale('eng');

		$article = $table->find('all')
			->select(['id'])
			->toArray();

		$this->assertNotNull($article, 'There will be an exception if there\'s ambiguous sql');

		$article = $table->find('all')
			->select(['title'])
			->toArray();

		$this->assertNotNull($article, 'There will be an exception if there\'s ambiguous sql');
	}

/**
 * testNoAmbiguousConditions
 *
 * @return void
 */
	public function testNoAmbiguousConditions() {
		$table = TableRegistry::get('Articles');
		$table->addBehavior('Translate', ['fields' => ['title', 'body']]);
		$table->locale('eng');

		$article = $table->find('all')
			->where(['id' => 1])
			->toArray();

		$this->assertNotNull($article, 'There will be an exception if there\'s ambiguous sql');

		$article = $table->find('all')
			->where(['title' => 1])
			->toArray();

		$this->assertNotNull($article, 'There will be an exception if there\'s ambiguous sql');
	}

/**
 * testNoAmbiguousOrder
 *
 * @return void
 */
	public function testNoAmbiguousOrder() {
		$table = TableRegistry::get('Articles');
		$table->addBehavior('Translate', ['fields' => ['title', 'body']]);
		$table->locale('eng');

		$article = $table->find('all')
			->order(['id' => 'asc'])
			->toArray();

		$this->assertNotNull($article, 'There will be an exception if there\'s ambiguous sql');

		$article = $table->find('all')
			->order(['title' => 'asc'])
			->toArray();

		$this->assertNotNull($article, 'There will be an exception if there\'s ambiguous sql');
	}

}
