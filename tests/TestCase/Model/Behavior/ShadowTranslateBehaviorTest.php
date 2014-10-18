<?php
namespace ShadowTranslate\Test\TestCase\Model\Behavior;

use ShadowTranslate\Model\Behavior\ShadowTranslateBehavior;
use Cake\TestSuite\TestCase;

/**
 * ShadowTranslate\Model\Behavior\ShadowTranslateBehavior Test Case
 */
class ShadowTranslateBehaviorTest extends TestCase {

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
		unset($this->ShadowTranslate);

		parent::tearDown();
	}

}
