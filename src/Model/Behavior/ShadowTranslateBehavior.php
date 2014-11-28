<?php
namespace ShadowTranslate\Model\Behavior;

use ArrayObject;
use Cake\Event\Event;
use Cake\Model\Behavior\TranslateBehavior;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * ShadowTranslate behavior
 */
class ShadowTranslateBehavior extends TranslateBehavior {

/**
 * Default configuration.
 *
 * @var array
 */
	protected $_defaultConfig = [];

/**
 * Constructor
 *
 * @param \Cake\ORM\Table $table Table instance
 * @param array $config Configuration
 */
	public function __construct(Table $table, array $config = []) {
		$config += [
			'alias' => $table->alias() . 'Translations',
			'translationTable' => $table->table() . '_translations',
			'fields' => '*',
			'joinType' => 'LEFT'
		];

		if ($config['fields'] === '*') {
			$translationTable = TableRegistry::get($config['alias']);
			$allFields = $translationTable->schema()->columns();
			$config['fields'] = array_values(array_diff($allFields, ['id', 'locale']));
		}

		parent::__construct($table, $config);
	}

/**
 * Create a hasMany association for all records
 *
 * Don't create a hasOne association here as the join conditions are modified
 * in before find - so create/modify it there
 *
 * @param array $fields list of fields to create associations for - ignored
 * @param string $table the table name to use for storing each field translation
 * @return void
 */
	public function setupFieldAssociations($fields, $table) {
		$config = $this->config();
		$alias = $this->_table->alias();

		$target = TableRegistry::get($config['alias']);
		$target->table($table);

		$this->_table->hasMany($table, [
			'foreignKey' => ['id'],
			'strategy' => 'subquery',
			'propertyName' => '_i18n',
			'dependent' => true
		]);
	}

/**
 * Callback method that listens to the `beforeFind` event in the bound
 * table. It modifies the passed query by eager loading the translated fields
 * and adding a formatter to copy the values into the main table records.
 *
 * @param \Cake\Event\Event $event The beforeFind event that was fired.
 * @param \Cake\ORM\Query $query Query
 * @return void
 */
	public function beforeFind(Event $event, Query $query) {
		$locale = $this->locale();

		if ($locale === $this->config('defaultLocale')) {
			return;
		}

		$config = $this->config();

		$this->_table->hasOne($config['alias'], [
			'foreignKey' => ['id'],
			'joinType' => $config['joinType'],
			'propertyName' => 'translation',
			'conditions' => [
				$config['alias'] . '.locale' => $locale
			],
		]);

		$query->contain([$config['alias']]);
		$this->_addFieldsToQuery($query, $config);
		$this->_aliasOrderForQuery($query, $config);

		$query->formatResults(function ($results) use ($locale) {
			return $this->_rowMapper($results, $locale);
		}, $query::PREPEND);
	}

/**
 * Modifies the entity before it is saved so that translated fields are persisted
 * in the database too.
 *
 * @param \Cake\Event\Event $event The beforeSave event that was fired
 * @param \Cake\ORM\Entity $entity The entity that is going to be saved
 * @param \ArrayObject $options the options passed to the save method
 * @return void
 */
	public function beforeSave(Event $event, Entity $entity, ArrayObject $options) {
		$locale = $entity->get('_locale') ?: $this->locale();
		$table = $this->_config['translationTable'];
		$newOptions = [$table => ['validate' => false]];
		$options['associated'] = $newOptions + $options['associated'];

		$this->_bundleTranslatedFields($entity);
		$bundled = $entity->get('_i18n') ?: [];

		if ($locale === $this->config('defaultLocale')) {
			return;
		}

		$values = $entity->extract($this->_config['fields'], true);
		$fields = array_keys($values);
		$primaryKey = (array)$this->_table->primaryKey();
		$key = $entity->get(current($primaryKey));

		$translation = TableRegistry::get($table)->find()
			->select(array_merge(['id', 'locale'], $fields))
			->where(['locale' => $locale, 'id' => $key])
			->bufferResults(false)
			->first();

		foreach ($fields as $field) {
			$translation->set($field, $values[$field]);
		}

		$entity->set('_i18n', array_merge($bundled, [$translation]));
		$entity->set('_locale', $locale, ['setter' => false]);
		$entity->dirty('_locale', false);

		foreach ($fields as $field) {
			$entity->dirty($field, false);
		}
	}

/**
 * Add translation fields to query
 *
 * If the query is using autofields (directly or implicitly) add the
 * main table's fields to the query first.
 *
 * Only add translations for fields that are in the main table, always
 * add the locale field though.
 *
 * @param Query $query the query to check
 * @param array $config the config to use for adding fields
 * @return void
 */
	protected function _addFieldsToQuery(Query $query, array $config) {
		$select = $query->clause('select');
		$addAll = false;

		if (!count($select) || $query->autoFields() === true) {
			$addAll = true;
			$query->select($query->repository()->schema()->columns());
			$select = $query->clause('select');
		}

		$alias = $this->_table->alias();
		foreach ($config['fields'] as $field) {
			if (
				$addAll ||
				in_array($field, $select, true) ||
				in_array("$alias.$field", $select, true)
			) {
				$query->select($query->aliasField($field, $config['alias']));
			}
		}
		$query->select($query->aliasField('locale', $config['alias']));
	}

/**
 * If a translated field is used without a model alias in a query, rewrite
 * the order clause to prevent ambiguous field sql errors.
 *
 * @param Query $query the query to check
 * @param array $config the config to use for adding fields
 * @return void
 */
	protected function _aliasOrderForQuery(Query $query, array $config) {
		$order = $query->clause('order');
		if (!$order || !$order->count()) {
			return;
		}

		$changed = false;
		$updated = [];

		$order->iterateParts(function ($c, $field) use (&$changed, &$updated, $config) {
			if (
				strpos($field, '.') ||
				!in_array($field, $config['fields'])
			) {
				$updated[$field] = $c;
				return;
			}

			$changeds = true;
			$field = "${config['alias']}.$field";
			$updated[$field] = $c;
		});

		if ($changed) {
			$query->order($updated, true);
		}
	}

/**
 * Modifies the results from a table find in order to merge the translated fields
 * into each entity for a given locale.
 *
 * @param \Cake\Datasource\ResultSetInterface $results Results to map.
 * @param string $locale Locale string
 * @return \Cake\Collection\Collection
 */
	protected function _rowMapper($results, $locale) {
		return $results->map(function ($row) {
			if ($row === null) {
				return $row;
			}

			$hydrated = !is_array($row);

			if (empty($row['translation'])) {
				$row['_locale'] = $this->locale();
				unset($row['translation']);

				if ($hydrated) {
					$row->clean();
				}
				return $row;
			}

			$translation = $row['translation'];

			$keys = $hydrated ? $translation->visibleProperties() : array_keys($translation);

			foreach ($keys as $field) {
				if ($field === 'locale') {
					$row['_locale'] = $translation[$field];
					continue;
				}

				$row[$field] = $translation[$field];
			}

			unset($row['translation']);

			if ($hydrated) {
				$row->clean();
			}

			return $row;
		});
	}

/**
 * Modifies the results from a table find in order to merge full translation records
 * into each entity under the `_translations` key
 *
 * @param \Cake\Datasource\ResultSetInterface $results Results to modify.
 * @return \Cake\Collection\Collection
 */
	public function groupTranslations($results) {
		return $results->map(function ($row) {
			$translations = (array)$row->get('_i18n');

			$result = [];
			foreach ($translations as $translation) {
				unset($translation['id']);
				$result[$translation['locale']] = $translation;
			}

			$options = ['setter' => false, 'guard' => false];
			$row->set('_translations', $result, $options);
			unset($row['_i18n']);
			$row->clean();
			return $row;
		});
	}

/**
 * Helper method used to generated multiple translated field entities
 * out of the data found in the `_translations` property in the passed
 * entity. The result will be put into its `_i18n` property
 *
 * @param \Cake\ORM\Entity $entity Entity
 * @return void
 */
	protected function _bundleTranslatedFields($entity) {
		$translations = (array)$entity->get('_translations');

		if (empty($translations) && !$entity->dirty('_translations')) {
			return;
		}

		$fields = $this->_config['fields'];
		$primaryKey = (array)$this->_table->primaryKey();
		$key = $entity->get(current($primaryKey));
		$find = [];

		foreach ($translations as $lang => $translation) {
			foreach ($fields as $field) {
				if (!$translation->dirty($field)) {
					continue;
				}
				$find[] = ['locale' => $lang, 'field' => $field, 'foreign_key' => $key];
				$contents[] = new Entity(['content' => $translation->get($field)], [
					'useSetters' => false
				]);
			}
		}

		if (empty($find)) {
			return;
		}

		$results = $this->_findExistingTranslations($find);
		$alias = $this->_table->alias();

		foreach ($find as $i => $translation) {
			if (!empty($results[$i])) {
				$contents[$i]->set('id', $results[$i], ['setter' => false]);
				$contents[$i]->isNew(false);
			} else {
				$translation['model'] = $alias;
				$contents[$i]->set($translation, ['setter' => false, 'guard' => false]);
				$contents[$i]->isNew(true);
			}
		}

		$entity->set('_i18n', $contents);
	}

}
