<?php
namespace ShadowTranslate\Model\Behavior;

use Cake\Event\Event;
use Cake\Model\Behavior\TranslateBehavior;
use Cake\ORM\Behavior;
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

		if ($this->_queryNeedsFieldsAdding($query, $config['fields'])) {
			$query->select($query->aliasFields($config['fields'], $config['alias']));
		}

		$query->formatResults(function ($results) use ($locale) {
			return $this->_rowMapper($results, $locale);
		}, $query::PREPEND);
	}

/**
 * Does the query need fields adding?
 *
 * @param Query $query the query to check
 * @param array $fields the fields to add
 * @return bool
 */
	protected function _queryNeedsFieldsAdding(Query $query, array $fields) {
		if ($query->autoFields() !== false) {
			return false;
		}

		$select = $query->clause('select');
		// TODO WIP
		debug ($select);
		die;
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
			if ($row === null || empty($row['translation'])) {
				return $row;
			}

			$hydrated = !is_array($row);
			$translation = $row['translation'];
			$keys = $hydrated ? $translation->visibleProperties() : array_keys($translation);

			foreach($keys as $field) {
				if ($field === 'id') {
					continue;
				}

				$value = $translation[$field];

				if ($field === 'locale') {
					$row['_locale'] = $value;
					continue;
				}

				$row[$field] = $value;
			}

			if ($hydrated) {
				$row->clean();
			}

			return $row;
		});
	}
}
