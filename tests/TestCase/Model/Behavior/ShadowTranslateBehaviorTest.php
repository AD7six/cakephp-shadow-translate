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
			1 => ['#1 ENG' => '#1 ENG body'],
			2 => ['#2 ENG' => '#2 ENG body'],
			3 => ['Third Article' => 'Third Article Body']
		];
		$this->assertSame($expected, $results);
	}

}
