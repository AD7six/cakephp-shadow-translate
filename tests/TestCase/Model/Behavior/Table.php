<?php
namespace ShadowTranslate\Test\TestCase\Model\Behavior;

use Cake\ORM\Table as CakeTable;

/**
 * Test Table class
 *
 * The sole purpose of this class is to hijack behavior loading to substitude
 * The translate behavior with the shadow translate behavior. This allows the
 * core test case to be used to verify as close as possible that the shadow
 * translate behavior is functionally equivalent to the core behavior.
 */
class Table extends CakeTable
{
    public function addBehavior($name, array $options = [])
    {
        if ($name === 'Translate') {
            $this->_behaviors->load('ShadowTranslate.ShadowTranslate', $options);
            // This is required because core's TranslateBehaviorTest uses
            // $table->behaviors->get('Translate')
            $this->_behaviors->set('Translate', $this->_behaviors->get('ShadowTranslate'));

            return;
        }

        parent::addBehavior($name, $options);
    }
}
