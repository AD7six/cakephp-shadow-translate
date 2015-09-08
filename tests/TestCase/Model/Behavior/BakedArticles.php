<?php
namespace ShadowTranslate\Test\TestCase\Model\Behavior;

use Cake\ORM\Entity;
use Cake\ORM\Behavior\Translate\TranslateTrait;

/**
 * Simulated baked entity class for a translated table
 *
 */
class BakedArticles extends Entity
{
    use TranslateTrait;

    protected $_accessible = [
        'title' => true,
        'body' => true
    ];
}
