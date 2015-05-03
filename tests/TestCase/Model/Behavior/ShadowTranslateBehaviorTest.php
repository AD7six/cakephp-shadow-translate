<?php
namespace ShadowTranslate\Test\TestCase\Model\Behavior;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Test\TestCase\ORM\Behavior\TranslateBehaviorTest;

/**
 * ShadowTranslateBehavior test case
 */
class ShadowTranslateBehaviorTest extends TranslateBehaviorTest
{
    public $fixtures = [
        'core.Articles',
        'core.Authors',
        'core.Comments',
        'plugin.ShadowTranslate.ArticlesTranslations',
        'plugin.ShadowTranslate.ArticlesMoreTranslations',
        'plugin.ShadowTranslate.AuthorsTranslations',
        'plugin.ShadowTranslate.CommentsTranslations',
    ];

    /**
     * Seed the table registry with this test case's Table class
     *
     * @return void
     */
    public function setUp()
    {
        $aliases = ['Articles', 'Authors', 'Comments'];
        $options = ['className' => 'ShadowTranslate\Test\TestCase\Model\Behavior\Table'];

        foreach ($aliases as $alias) {
            TableRegistry::get($alias, $options);
        }

        parent::setUp();
    }

    /**
     * Make sure the test Table class addBehavior method works
     *
     * A sanity test to make sure that the test method to add the translate
     * behavior actually adds the shadow translate behavior. If this test
     * fails, all other tests should also fail (because, this test class does
     * not import core.translates fixture on which the Translate behavior
     * test would otherwise depend).
     *
     * @return void
     */
    public function testTestSetup()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');

        $this->assertFalse($table->hasBehavior('Translate'), 'Should not be on this table');
        $this->assertTrue($table->hasBehavior('ShadowTranslate'), 'Should be on this table');
    }

    /**
     * Check things are setup correctly by default
     *
     * The hasOneAlias is used for the has-one translation, the hasManyAlias is used
     * with findTranslations
     *
     * @return void
     */
    public function testDefaultAliases()
    {
        $table = TableRegistry::get('Articles');
        $table->table();
        $table->addBehavior('Translate');

        $config = $table->behaviors()->get('ShadowTranslate')->config();
        $wantedKeys = [
            'translationTable',
            'mainTableAlias',
            'hasOneAlias',
            'hasManyAlias',
        ];
        $config = array_intersect_key($config, array_flip($wantedKeys));
        $expected = [
            'translationTable' => 'ArticlesTranslations',
            'mainTableAlias' => 'Articles',
            'hasOneAlias' => 'ArticlesTranslation',
            'hasManyAlias' => 'ArticlesTranslations'
        ];
        $this->assertSame($expected, $config, 'Used aliases should match the main table object');

        $this->_testFind();
    }

    /**
     * Check things are setup correctly by default for plugin models
     *
     * @return void
     */
    public function testDefaultPluginAliases()
    {
        $table = TableRegistry::get(
            'SomeRandomPlugin.Articles',
            ['className' => 'ShadowTranslate\Test\TestCase\Model\Behavior\Table']
        );

        $table->table();
        $table->addBehavior('Translate');

        $config = $table->behaviors()->get('ShadowTranslate')->config();
        $wantedKeys = [
            'translationTable',
            'mainTableAlias',
            'hasOneAlias',
            'hasManyAlias',
        ];
        $config = array_intersect_key($config, array_flip($wantedKeys));
        $expected = [
            'translationTable' => 'SomeRandomPlugin.ArticlesTranslations',
            'mainTableAlias' => 'Articles',
            'hasOneAlias' => 'ArticlesTranslation',
            'hasManyAlias' => 'ArticlesTranslations'
        ];
        $this->assertSame($expected, $config, 'Used aliases should match the main table object');

        $exists = TableRegistry::exists('SomeRandomPlugin.ArticlesTranslations');
        $this->assertTrue($exists, 'The behavior should have populated this key with a table object');

        $translationTable = TableRegistry::get('SomeRandomPlugin.ArticlesTranslations');
        $this->assertSame(
            'SomeRandomPlugin.ArticlesTranslations',
            $translationTable->registryAlias(),
            'It should be a different object to the one in the no-plugin prefix'
        );

        $this->_testFind('SomeRandomPlugin.Articles');
    }

    /**
     * testChangingReferenceName
     *
     * The parent test is EAV specific. Test that the config reflects the referenceName -
     * which is used to determine the the translation table/association name only in the
     * shadow translate behavior
     *
     * @return void
     */
    public function testChangingReferenceName()
    {
        $table = TableRegistry::get('Articles');
        $table->table();
        $table->addBehavior(
            'Translate',
            ['referenceName' => 'Posts']
        );

        $config = $table->behaviors()->get('ShadowTranslate')->config();
        $wantedKeys = [
            'translationTable',
            'mainTableAlias',
            'hasOneAlias',
            'hasManyAlias',
        ];

        $config = array_intersect_key($config, array_flip($wantedKeys));
        $expected = [
            'translationTable' => 'PostsTranslations',
            'mainTableAlias' => 'Articles',
            'hasOneAlias' => 'ArticlesTranslation',
            'hasManyAlias' => 'ArticlesTranslations'
        ];
        $this->assertSame($expected, $config, 'The translationTable key should be derived from referenceName');
    }

    /**
     * Allow usage without specifying fields explicitly
     *
     * Fields are only detected when necessary, one of those times is a fine with fields.
     *
     * @return void
     */
    public function testAutoFieldDetection()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');

        $table->locale('eng');
        $table->find()->select(['title'])->first();

        $expected = ['title', 'body'];
        $result = $table->behaviors()->get('ShadowTranslate')->config('fields');
        $this->assertSame(
            $expected,
            $result,
            'If no fields are specified, they should be derived from the schema'
        );
    }

    /**
     * testTranslationTableConfig
     *
     * @return void
     */
    public function testTranslationTableConfig()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');

        $exists = TableRegistry::exists('ArticlesTranslations');
        $this->assertTrue($exists, 'The table registry should have an object in this key now');

        $translationTable = TableRegistry::get('ArticlesTranslations');
        $this->assertSame('articles_translations', $translationTable->table());
        $this->assertSame('ArticlesTranslations', $translationTable->alias());
    }

    /**
     * Only join translations when necessary
     *
     * By inspecting the sql generated, verify that if there is a need for the translation
     * table to be included in the query it is present, and when there is no clear need -
     * that it is not.
     *
     * @return void
     */
    public function testNoUnnecessaryJoins()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');

        $query = $table->find();
        $this->assertNotContains(
            'articles_translations',
            $query->sql(),
            'The default locale doesn\'t need a join'
        );

        $table->locale('eng');

        $query = $table->find()->select(['id']);
        $this->assertNotContains(
            'articles_translations',
            $query->sql(),
            'No translated fields, nothing to do'
        );

        $query = $table->find()->select(['Other.title']);
        $this->assertNotContains(
            'articles_translations',
            $query->sql(),
            'Other isn\'t the table class with the translate behavior, nothing to do'
        );
    }

    /**
     * Join when translations are necessary
     *
     * @return void
     */
    public function testNecessaryJoinsSelect()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
        $table->locale('eng');

        $query = $table->find();
        $this->assertContains(
            'articles_translations',
            $query->sql(),
            'No fields specified, means select all fields - translated included'
        );

        $query = $table->find()->select(['title']);
        $this->assertContains(
            'articles_translations',
            $query->sql(),
            'Selecting a translated field should join the translations table'
        );

        $query = $table->find()->select(['Articles.title']);
        $this->assertContains(
            'articles_translations',
            $query->sql(),
            'Selecting an aliased translated field should join the translations table'
        );
    }

    /**
     * Join when translations are necessary
     *
     * @return void
     */
    public function testNecessaryJoinsWhere()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
        $table->locale('eng');

        $query = $table->find()->select(['id'])->where(['title' => 'First Article']);
        $this->assertContains(
            'articles_translations',
            $query->sql(),
            'If the where clause includes a translated field - a join is required'
        );
    }

    /**
     * Join when translations are necessary
     *
     * @return void
     */
    public function testNecessaryJoinsOrder()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
        $table->locale('eng');

        $query = $table->find()->select(['id'])->order(['title' => 'desc']);
        $this->assertContains(
            'articles_translations',
            $query->sql(),
            'If the order clause includes a translated field - a join is required'
        );

        $query = $table->find();
        $this->assertContains(
            'articles_translations',
            $query->sql(),
            'No fields means auto-fields - a join is required'
        );
    }

    /**
     * Setup a contrived self join and make sure both records are translated
     *
     * Different locales are used on each table object just to make any resulting
     * confusion easier to identify as neither the original or translated values
     * overlap between the two records.
     *
     * @return void
     */
    public function testSelfJoin()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
        $table->locale('eng');

        $table->belongsTo('Copy', ['className' => 'Articles', 'foreignKey' => 'author_id']);
        $table->Copy->addBehavior('ShadowTranslate.ShadowTranslate');
        $table->Copy->locale('deu');

        $query = $table->find()
            ->where(['Articles.id' => 3])
            ->contain('Copy');

        $result = $query->first()->toArray();
        $expected = [
            'id' => 3,
            'author_id' => 1,
            'title' => 'Title #3',
            'body' => 'Content #3',
            'published' => 'Y',
            'copy' => [
                'id' => '1',
                'author_id' => 1,
                'title' => 'Titel #1',
                'body' => 'Inhalt #1',
                'published' => 'Y',
                '_locale' => 'deu'
            ],
            '_locale' => 'eng'
        ];
        $this->assertEquals(
            $expected,
            $result,
            'The copy record should also be translated'
        );
    }

    /**
     * Verify it is not necessary for a translated field to exist in the master table
     *
     * @return void
     */
    public function testVirtualTranslationField()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate', [
            'translationTableAlias' => 'ArticlesMoreTranslations',
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
    public function testDelete()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
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
    public function testNoAmbiguousFields()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
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
    public function testNoAmbiguousConditions()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
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
    public function testNoAmbiguousOrder()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
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

    /**
     * If results are unhydrated, it should still work
     *
     * @return void
     */
    public function testUnhydratedResults()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('ShadowTranslate.ShadowTranslate');

        $result = $table
            ->find('translations')
            ->hydrate(false)
            ->first();
        $this->assertArrayHasKey('title', $result);
    }

    /**
     * A find containing another association should act the same whether translated or not
     *
     * @return void
     */
    public function testFindWithAssociations()
    {
        $table = TableRegistry::get('Articles');
        $table->belongsTo('Authors');

        $table->addBehavior('Translate');
        $table->locale('eng');

        $query = $table
            ->find('translations')
            ->where(['Articles.id' => 1])
            ->contain(['Authors']);
        $this->assertContains(
            'articles_translations',
            $query->sql(),
            'There should be a join to the translations table'
        );

        $result = $query->firstOrFail();

        $this->assertNotNull($result->author, "There should be an author for article 1.");
        $expected = [
            'id' => 1,
            'name' => 'mariano'
        ];
        $this->assertSame($expected, $result->author->toArray());

        $this->assertNotEmpty($result->_translations, "Translations can't be empty.");
    }

    /**
     * testFindTranslations
     *
     * The parent test expects description translations in only some of the records
     * that's incompatible with the shadow-translate behavior, since the schema
     * dictates what fields to expect to be translated and doesnt permit any EAV
     * style translations
     *
     * @return void
     */
    public function testFindTranslations()
    {
        $this->assertTrue(true, 'Skipped');
    }

    /**
     * By default empty translations should be honored
     *
     * @return void
     */
    public function testEmptyTranslationsDefaultBehavior()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate');
        $table->locale('zzz');
        $result = $table->get(1);

        $this->assertSame('', $result->title, 'The empty translation should be used');
        $this->assertSame('', $result->body, 'The empty translation should be used');
        $this->assertNull($result->description);
    }

    /**
     * Tests that allowEmptyTranslations takes effect
     *
     * @return void
     */
    public function testEmptyTranslations()
    {
        $table = TableRegistry::get('Articles');
        $table->addBehavior('Translate', [
            'allowEmptyTranslations' => false,
        ]);
        $table->locale('zzz');
        $result = $table->get(1);

        $this->assertSame('First Article', $result->title, 'The empty translation should be ignored');
        $this->assertSame('First Article Body', $result->body, 'The empty translation should be ignored');
        $this->assertNull($result->description);
    }

    /**
     * Used in the config tests to verify that a simple find still works
     *
     * @param string $tableAlias
     * @return void
     */
    protected function _testFind($tableAlias = 'Articles')
    {
        $table = TableRegistry::get($tableAlias);
        $table->locale('eng');

        $query = $table->find()->select();
        $result = array_intersect_key(
            $query->first()->toArray(),
            array_flip(['title', 'body', '_locale'])
        );
        $expected = [
            'title' => 'Title #1',
            'body' => 'Content #1',
            '_locale' => 'eng'
        ];
        $this->assertSame(
            $expected,
            $result,
            'Title and body are translated values, but don\'t match'
        );
    }
}
