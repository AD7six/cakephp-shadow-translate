<?php
namespace ShadowTranslate\Test\TestCase\Model\Behavior;

use ShadowTranslate\Model\Behavior\ShadowTranslateBehavior;
use Cake\TestSuite\TestCase;

/**
 * ShadowTranslate\Model\Behavior\ShadowTranslateBehavior Test Case
 */
class ShadowTranslateBehaviorTest extends TestCase {

/**
 * fixtures
 *
 * @var array
 */
	public $fixtures = [
		'core.articles',
		'shadowTranslate.ShadowArticles',
	];

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$this->ShadowTranslate = new ShadowTranslateBehavior();
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->ShadowTranslate);
		I18n::locale(I18n::defaultLocale());
		TableRegistry::clear();
	}

/**
 * Tests that fields from a translated model are overriden
 *
 * @return void
 */
	public function testFindSingleLocale() {
		$table = TableRegistry::get('Articles');
		$table->addBehavior('ShadowTranslate.ShadowTranslate', ['fields' => ['title', 'body']]);
		$table->locale('eng');
		$results = $table->find()->combine('title', 'body', 'id')->toArray();
		$expected = [
			1 => ['Title #1' => 'Content #1'],
			2 => ['Title #2' => 'Content #2'],
			3 => ['Title #3' => 'Content #3'],
		];
		$this->assertSame($expected, $results);
	}
}
