<?php
namespace ShadowTranslate\Test\TestCase\Model\Behavior;

use Cake\I18n\I18n;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * ShadowTranslate\Model\Behavior\ShadowTranslateBehavior Test Case
 */
class ShadowTranslateBehaviorTest extends TestCase {

	public $fixtures = [
		'core.articles',
		'core.comments',
		'core.authors',
		'plugin.ShadowTranslate.ArticlesTranslations'
	];

	public function tearDown() {
		parent::tearDown();
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
		$table->addBehavior('ShadowTranslate.ShadowTranslate');
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
