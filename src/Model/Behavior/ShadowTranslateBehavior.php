<?php
namespace ShadowTranslate\Model\Behavior;

use ArrayObject;
use Cake\Database\Expression\FieldInterface;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\I18n\I18n;
use Cake\ORM\Behavior\TranslateBehavior;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * ShadowTranslate behavior
 */
class ShadowTranslateBehavior extends TranslateBehavior
{
    /**
     * Constructor
     *
     * @param \Cake\ORM\Table $table Table instance
     * @param array $config Configuration
     */
    public function __construct(Table $table, array $config = [])
    {
        $this->_defaultConfig['implementedMethods'] += [
            'setLocale' => 'setLocale',
            'getLocale' => 'getLocale',
        ];

        $tableAlias = $table->getAlias();
        list($plugin) = pluginSplit($table->getRegistryAlias(), true);

        if (isset($config['referenceName'])) {
            $tableReferenceName = $config['referenceName'];
        } else {
            $tableReferenceName = $this->_referenceName($table);
        }

        $config += [
            'mainTableAlias' => $tableAlias,
            'translationTable' => $plugin . $tableReferenceName . 'Translations',
            'hasOneAlias' => $tableAlias . 'Translation',
        ];

        parent::__construct($table, $config);
    }

    /**
     * Sets the locale that should be used for all future find and save operations on
     * the table where this behavior is attached to.
     *
     * When fetching records, the behavior will include the content for the locale set
     * via this method, and likewise when saving data, it will save the data in that
     * locale.
     *
     * Note that in case an entity has a `_locale` property set, that locale will win
     * over the locale set via this method (and over the globally configured one for
     * that matter)!
     *
     * @param string|null $locale The locale to use for fetching and saving records. Pass `null`
     * in order to unset the current locale, and to make the behavior fall back to using the
     * globally configured locale.
     * @return $this
     * @see \ShadowTranslate\ORM\Behavior\ShadowTranslateBehavior::getLocale()
     */
    public function setLocale($locale)
    {
        $this->_locale = $locale;

        return $this;
    }

    /**
     * Returns the current locale.
     *
     * If no locale has been explicitly set via `setLocale()`, this method will return
     * the currently configured global locale.
     *
     * @return string
     * @see \Cake\I18n\I18n::getLocale()
     * @see \ShadowTranslate\ORM\Behavior\ShadowTranslateBehavior::setLocale()
     */
    public function getLocale()
    {
        return $this->_locale ?: I18n::getLocale();
    }

    /**
     * Create a hasMany association for all records
     *
     * Don't create a hasOne association here as the join conditions are modified
     * in before find - so create/modify it there
     *
     * @param array $fields - ignored
     * @param string $table - ignored
     * @param string $fieldConditions - ignored
     * @param string $strategy the strategy used in the _i18n association
     *
     * @return void
     */
    public function setupFieldAssociations($fields, $table, $fieldConditions, $strategy)
    {
        $config = $this->getConfig();

        $this->_table->hasMany($config['translationTable'], [
            'className' => $config['translationTable'],
            'foreignKey' => 'id',
            'strategy' => $strategy,
            'propertyName' => '_i18n',
            'dependent' => true,
        ]);
    }

    /**
     * Callback method that listens to the `beforeFind` event in the bound
     * table. It modifies the passed query by eager loading the translated fields
     * and adding a formatter to copy the values into the main table records.
     *
     * @param \Cake\Event\Event $event The beforeFind event that was fired.
     * @param \Cake\ORM\Query $query Query
     * @param \ArrayObject $options The options for the query
     * @return void
     */
    public function beforeFind(Event $event, Query $query, ArrayObject $options)
    {
        $locale = $this->getLocale();

        if ($locale === $this->getConfig('defaultLocale')) {
            return;
        }

        $config = $this->getConfig();

        if (isset($options['filterByCurrentLocale'])) {
            $joinType = $options['filterByCurrentLocale'] ? 'INNER' : 'LEFT';
        } else {
            $joinType = $config['onlyTranslated'] ? 'INNER' : 'LEFT';
        }

        $this->_table->hasOne($config['hasOneAlias'], [
            'foreignKey' => ['id'],
            'joinType' => $joinType,
            'propertyName' => 'translation',
            'className' => $config['translationTable'],
            'conditions' => [
                $config['hasOneAlias'] . '.locale' => $locale,
            ],
        ]);

        $fieldsAdded = $this->_addFieldsToQuery($query, $config);
        $orderByTranslatedField = $this->_iterateClause($query, 'order', $config);
        $filteredByTranslatedField = $this->_traverseClause($query, 'where', $config);

        if (!$fieldsAdded && !$orderByTranslatedField && !$filteredByTranslatedField) {
            return;
        }

        $query->contain([$config['hasOneAlias']]);

        $query->formatResults(function ($results) use ($locale) {
            return $this->_rowMapper($results, $locale);
        }, $query::PREPEND);
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
     * @param \Cake\ORM\Query $query the query to check
     * @param array $config the config to use for adding fields
     * @return bool Whether a join to the translation table is required
     */
    protected function _addFieldsToQuery(Query $query, array $config)
    {
        if ($query->isAutoFieldsEnabled()) {
            return true;
        }

        $select = array_filter($query->clause('select'), function ($field) {
            return is_string($field);
        });

        if (!$select) {
            return true;
        }

        $alias = $config['mainTableAlias'];
        $joinRequired = false;
        foreach ($this->_translationFields() as $field) {
            if (array_intersect($select, [$field, "$alias.$field"])) {
                $joinRequired = true;
                $query->select($query->aliasField($field, $config['hasOneAlias']));
            }
        }

        if ($joinRequired) {
            $query->select($query->aliasField('locale', $config['hasOneAlias']));
        }

        return $joinRequired;
    }

    /**
     * Iterate over a clause to alias fields
     *
     * The objective here is to transparently prevent ambiguous field errors by
     * prefixing fields with the appropriate table alias. This method currently
     * expects to receive an order clause only.
     *
     * @param \Cake\ORM\Query $query the query to check
     * @param string $name The clause name
     * @param array $config the config to use for adding fields
     * @return bool Whether a join to the translation table is required
     */
    protected function _iterateClause(Query $query, $name = '', $config = [])
    {
        $clause = $query->clause($name);
        if (!$clause || !$clause->count()) {
            return false;
        }

        $alias = $config['hasOneAlias'];
        $fields = $this->_translationFields();
        $mainTableAlias = $config['mainTableAlias'];
        $mainTableFields = $this->_mainFields();
        $joinRequired = false;

        $clause->iterateParts(function ($c, &$field) use ($fields, $alias, $mainTableAlias, $mainTableFields, &$joinRequired) {
            if (!is_string($field) || strpos($field, '.')) {
                return $c;
            }

            if (in_array($field, $fields)) {
                $joinRequired = true;
                $field = "$alias.$field";
            } elseif (in_array($field, $mainTableFields)) {
                $field = "$mainTableAlias.$field";
            }

            return $c;
        });

        return $joinRequired;
    }

    /**
     * Traverse over a clause to alias fields
     *
     * The objective here is to transparently prevent ambiguous field errors by
     * prefixing fields with the appropriate table alias. This method currently
     * expects to receive a where clause only.
     *
     * @param \Cake\ORM\Query $query the query to check
     * @param string $name The clause name
     * @param array $config the config to use for adding fields
     * @return bool Whether a join to the translation table is required
     */
    protected function _traverseClause(Query $query, $name = '', $config = [])
    {
        $clause = $query->clause($name);
        if (!$clause || !$clause->count()) {
            return false;
        }

        $alias = $config['hasOneAlias'];
        $fields = $this->_translationFields();
        $mainTableAlias = $config['mainTableAlias'];
        $mainTableFields = $this->_mainFields();
        $joinRequired = false;

        $clause->traverse(function ($expression) use ($fields, $alias, $mainTableAlias, $mainTableFields, &$joinRequired) {
            if (!($expression instanceof FieldInterface)) {
                return;
            }
            $field = $expression->getField();
            if (!is_string($field) || strpos($field, '.')) {
                return;
            }

            if (in_array($field, $fields)) {
                $joinRequired = true;
                $expression->setField("$alias.$field");

                return;
            }

            if (in_array($field, $mainTableFields)) {
                $expression->setField("$mainTableAlias.$field");
            }
        });

        return $joinRequired;
    }

    /**
     * Modifies the entity before it is saved so that translated fields are persisted
     * in the database too.
     *
     * @param \Cake\Event\Event $event The beforeSave event that was fired
     * @param \Cake\Datasource\EntityInterface $entity The entity that is going to be saved
     * @param \ArrayObject $options the options passed to the save method
     * @return void
     */
    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        $locale = $entity->get('_locale') ?: $this->getLocale();
        $newOptions = [$this->_translationTable->getAlias() => ['validate' => false]];
        $options['associated'] = $newOptions + $options['associated'];

        // Check early if empty translations are present in the entity.
        // If this is the case, unset them to prevent persistence.
        // This only applies if $this->_config['allowEmptyTranslations'] is false
        if ($this->_config['allowEmptyTranslations'] === false) {
            $this->_unsetEmptyFields($entity);
        }

        $this->_bundleTranslatedFields($entity);
        $bundled = $entity->get('_i18n') ?: [];
        $noBundled = count($bundled) === 0;

        // No additional translation records need to be saved,
        // as the entity is in the default locale.
        if ($noBundled && $locale === $this->getConfig('defaultLocale')) {
            return;
        }

        $values = $entity->extract($this->_translationFields(), true);
        $fields = array_keys($values);
        $noFields = empty($fields);

        // If there are no fields and no bundled translations, or both fields
        // in the default locale and bundled translations we can
        // skip the remaining logic as its not necessary.
        if ($noFields && $noBundled || ($fields && $bundled)) {
            return;
        }

        $primaryKey = (array)$this->_table->getPrimaryKey();
        $id = $entity->get(current($primaryKey));

        // When we have no key and bundled translations, we
        // need to mark the entity dirty so the root
        // entity persists.
        if ($noFields && $bundled && !$id) {
            foreach ($this->_translationFields() as $field) {
                $entity->setDirty($field, true);
            }

            return;
        }

        if ($noFields) {
            return;
        }

        $where = compact('id', 'locale');

        $translation = $this->_translationTable()->find()
            ->select(array_merge(['id', 'locale'], $fields))
            ->where($where)
            ->enableBufferedResults(false)
            ->first();

        if ($translation) {
            foreach ($fields as $field) {
                $translation->set($field, $values[$field]);
            }
        } else {
            $translation = $this->_translationTable()->newEntity(
                $where + $values,
                [
                    'useSetters' => false,
                    'markNew' => true,
                ]
            );
        }

        $entity->set('_i18n', array_merge($bundled, [$translation]));
        $entity->set('_locale', $locale, ['setter' => false]);
        $entity->setDirty('_locale', false);

        foreach ($fields as $field) {
            $entity->setDirty($field, false);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function buildMarshalMap($marshaller, $map, $options)
    {
        $this->_translationFields();

        return parent::buildMarshalMap($marshaller, $map, $options);
    }

    /**
     * Returns a fully aliased field name for translated fields.
     *
     * If the requested field is configured as a translation field, field with
     * an alias of a corresponding association is returned. Table-aliased
     * field name is returned for all other fields.
     *
     * @param string $field Field name to be aliased.
     * @return string
     */
    public function translationField($field)
    {
        if ($this->getLocale() === $this->getConfig('defaultLocale')) {
            return $this->_table->aliasField($field);
        }

        $translatedFields = $this->_translationFields();
        if (in_array($field, $translatedFields)) {
            return $this->getConfig('hasOneAlias') . '.' . $field;
        }

        return $this->_table->aliasField($field);
    }

    /**
     * Modifies the results from a table find in order to merge the translated fields
     * into each entity for a given locale.
     *
     * @param \Cake\Datasource\ResultSetInterface $results Results to map.
     * @param string $locale Locale string
     * @return \Cake\Collection\CollectionInterface
     */
    protected function _rowMapper($results, $locale)
    {
        $allowEmpty = $this->_config['allowEmptyTranslations'];

        return $results->map(function ($row) use ($allowEmpty) {
            if ($row === null) {
                return $row;
            }

            $hydrated = !is_array($row);

            if (empty($row['translation'])) {
                $row['_locale'] = $this->getLocale();
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

                if ($translation[$field] !== null) {
                    if ($allowEmpty || $translation[$field] !== '') {
                        $row[$field] = $translation[$field];
                    }
                }
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
     * @return \Cake\Collection\CollectionInterface
     */
    public function groupTranslations($results)
    {
        return $results->map(function ($row) {
            $translations = (array)$row['_i18n'];
            if (count($translations) === 0 && $row->get('_translations')) {
                return $row;
            }

            $result = [];
            foreach ($translations as $translation) {
                unset($translation['id']);
                $result[$translation['locale']] = $translation;
            }

            $row['_translations'] = $result;
            unset($row['_i18n']);
            if ($row instanceof EntityInterface) {
                $row->clean();
            }

            return $row;
        });
    }

    /**
     * Helper method used to generated multiple translated field entities
     * out of the data found in the `_translations` property in the passed
     * entity. The result will be put into its `_i18n` property
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @return void
     */
    protected function _bundleTranslatedFields($entity)
    {
        $translations = (array)$entity->get('_translations');

        if (empty($translations) && !$entity->isDirty('_translations')) {
            return;
        }

        $primaryKey = (array)$this->_table->getPrimaryKey();
        $key = $entity->get(current($primaryKey));

        foreach ($translations as $lang => $translation) {
            if (!$translation->id) {
                $update = [
                    'id' => $key,
                    'locale' => $lang,
                ];
                $translation->set($update, ['guard' => false]);
            }
        }

        $entity->set('_i18n', $translations);
    }

    /**
     * Based on the passed config, return the translation table instance
     *
     * If the table already exists in the registry - don't pass any config
     * as that'll just lead to an exception trying to reconfigure an existing
     * table.
     *
     * @param array $config behavior config to use
     * @return \Cake\ORM\Table Translation table instance
     */
    protected function _translationTable($config = [])
    {
        if (!$config) {
            $config = $this->getConfig();
        }

        return TableRegistry::get($config['translationTable']);
    }

    /**
     * Lazy define and return the main table fields
     *
     * @return array
     */
    protected function _mainFields()
    {
        $fields = $this->getConfig('mainTableFields');

        if ($fields) {
            return $fields;
        }

        $table = $this->_table;
        $fields = $table->getSchema()->columns();

        $this->setConfig('mainTableFields', $fields);

        return $fields;
    }

    /**
     * Lazy define and return the translation table fields
     *
     * @return array
     */
    protected function _translationFields()
    {
        $fields = $this->getConfig('fields');

        if ($fields) {
            return $fields;
        }

        $table = $this->_translationTable();
        $fields = $table->getSchema()->columns();
        $fields = array_values(array_diff($fields, ['id', 'locale']));

        $this->setConfig('fields', $fields);

        return $fields;
    }
}
