<?php

namespace ride\library\orm\model;

use ride\library\database\manipulation\condition\Condition;
use ride\library\database\manipulation\condition\SimpleCondition;
use ride\library\database\manipulation\expression\FieldExpression;
use ride\library\database\manipulation\expression\ScalarExpression;
use ride\library\database\manipulation\expression\SqlExpression;
use ride\library\database\manipulation\expression\TableExpression;
use ride\library\database\manipulation\statement\DeleteStatement;
use ride\library\database\manipulation\statement\InsertStatement;
use ride\library\database\manipulation\statement\UpdateStatement;
use ride\library\database\manipulation\statement\SelectStatement;
use ride\library\event\EventManager;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\entry\EntryCollection;
use ride\library\orm\entry\EntryProxy;
use ride\library\orm\entry\Entry;
use ride\library\orm\entry\LocalizedEntry;
use ride\library\orm\exception\ModelException;
use ride\library\orm\meta\ModelMeta;
use ride\library\orm\query\ModelQuery;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\ValidationError;

use \Exception;

/**
 * Basic implementation of a data model
 */
class GenericModel extends AbstractModel {

    /**
     * Event triggered before an insert action
     * @var string
     */
    const EVENT_INSERT_PRE = 'orm.insert.pre';

    /**
     * Event triggered after an insert action
     * @var string
     */
    const EVENT_INSERT_POST = 'orm.insert.post';

    /**
     * Event triggered before an update action
     * @var string
     */
    const EVENT_UPDATE_PRE = 'orm.update.pre';

    /**
     * Event triggered after an update action
     * @var string
     */
    const EVENT_UPDATE_POST = 'orm.update.post';

    /**
     * Event triggered before a delete action
     * @var string
     */
    const EVENT_DELETE_PRE = 'orm.delete.pre';

    /**
     * Event triggered after a delete action
     * @var string
     */
    const EVENT_DELETE_POST = 'orm.delete.post';

    /**
     * Instance of the event manager
     * @var \ride\library\event\EventManager
     */
    protected $eventManager;

    /**
     * Fieldname to order the data list
     * @var string
     */
    protected $findOrderField;

    /**
     * Order direction of the data list
     * @var string
     */
    protected $findOrderDirection;

    /**
     * Initializes the save stack
     * @return null
     */
    protected function initialize() {
        $this->saveStack = array();
        $this->findOrderField = $this->meta->getOption('order.field');
        $this->findOrderDirection = $this->meta->getOption('order.direction', 'ASC');
    }

    /**
     * Serializes this model
     * @return string Serialized model
     */
    public function serialize() {
        $serialize = array(
            'parent' => parent::serialize(),
            'orderField' => $this->findOrderField,
            'orderDirection' => $this->findOrderDirection,
        );

        return serialize($serialize);
    }

    /**
     * Unserializes the provided string into a model
     * @param string $serialized Serialized string of a model
     * @return null
     */
    public function unserialize($serialized) {
        $unserialized = unserialize($serialized);

        parent::unserialize($unserialized['parent']);

        $this->findOrderField = $unserialized['orderField'];
        $this->findOrderDirection = $unserialized['orderDirection'];
        $this->saveStack = array();
    }

    /**
     * Sets an instance of the event manager to this model
     * @param \ride\library\event\EventManager $eventManager
     * @return null
     */
    public function setEventManager(EventManager $eventManager) {
        $this->eventManager = $eventManager;
    }

    /**
     * Gets an entry by it's primary key
     * @param integer|string $id Id of the entry
     * @param string $locale Locale code
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @param integer $recursiveDepth Recursive depth of the query
     * @return mixed Instance of the entry if found, null otherwise
     */
    public function getById($id, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0) {
        $query = $this->createQuery($locale);
        $query->setRecursiveDepth($recursiveDepth);
        $query->setIncludeUnlocalized(true);
        $query->setFetchUnlocalized($fetchUnlocalized);

        $query->addCondition('{' . ModelTable::PRIMARY_KEY . '} = %1%', $id);

        return $query->queryFirst();
    }

    /**
     * Finds an entry in this model
     * @param array $options Options for the query
     * <ul>
     * <li>filter: array with the field name as key and the filter value as
     * value</li>
     * <li>match: array with the field name as key and the search query as
     * value</li>
     * <li>order: array with field and direction as key</li>
     * </li>
     * @param string $locale Locale code
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @param integer $recursiveDepth Recursive depth of the query
     * @return mixed Instance of the entry if found, null otherwise
     */
    public function getBy(array $options, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0) {
        $query = $this->createFindQuery($options, $locale, $fetchUnlocalized, $recursiveDepth);

        return $query->queryFirst();
    }

    /**
     * Finds entries in this model
     * @param array $options Options for the query
     * <ul>
     * <li>condition: array with condition strings or arrays with the condition
     * string as first element and condition arguments for the remaining
     * elements</li>
     * <li>filter: array with the field name as key and the filter value as
     * value</li>
     * <li>match: array with the field name as key and the search query as
     * value</li>
     * <li>order: array with field and direction as key</li>
     * <li>limit: number of entries to fetch</li>
     * <li>page: page number</li>
     * </li>
     * @param string $locale Code of the locale
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @param integer $recursiveDepth Recursive depth of the query
     * @param string $indexOn Name of the field to use for the key in the result
     * @return array
     */
    public function find(array $options = null, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0, $indexOn = null) {
        $query = $this->createFindQuery($options, $locale, $fetchUnlocalized, $recursiveDepth);

        if (isset($options['limit'])) {
            $page = isset($options['page']) ? $options['page'] : 1;

            $query->setLimit($options['limit'], $options['limit'] * ($page - 1));
        }

        return $query->query($indexOn);
    }

    /**
     * Finds entries in this model with the total
     * @param array $options Options for the query
     * <ul>
     * <li>condition: array with condition strings or arrays with the condition
     * string as first element and condition arguments for the remaining
     * elements</li>
     * <li>filter: array with the field name as key and the filter value as
     * value</li>
     * <li>match: array with the field name as key and the search query as
     * value</li>
     * <li>order: array with field and direction as key</li>
     * <li>limit: number of entries to fetch</li>
     * <li>page: page number</li>
     * </li>
     * @param string $locale Code of the locale
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @param integer $recursiveDepth Recursive depth of the query
     * @return \ride\library\orm\entry\EntryCollection
     */
    public function collect(array $options = null, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0) {
        $query = $this->createFindQuery($options, $locale, $fetchUnlocalized, $recursiveDepth);

        if (isset($options['limit'])) {
            $page = isset($options['page']) ? $options['page'] : 1;

            $query->setLimit($options['limit'], $options['limit'] * ($page - 1));

            $entries = $query->query();
            $total = $query->count();
        } else {
            $entries = $query->query();
            $total = count($entries);
        }

        return new EntryCollection($entries, $total);
    }

    /**
     * Gets the find query for this model
     * @param array $options Options for the query
     * <ul>
     * <li>condition: array with condition strings or arrays with the condition
     * string as first element and condition arguments for the remaining
     * elements</li>
     * <li>filter: array with the field name as key and the filter value as
     * value</li>
     * <li>match: array with the field name as key and the search query as
     * value</li>
     * <li>order: array with field and direction key</li>
     * </li>
     * @param string $locale Code of the locale
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @param integer $recursiveDepth Recursive depth of the query
     * @return \ride\library\orm\query\ModelQuery
     */
    protected function createFindQuery(array $options = null, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0) {
        $query = $this->createQuery($locale);
        $query->setRecursiveDepth($recursiveDepth);
        $query->setFetchUnlocalized($fetchUnlocalized);

        if (isset($options['distinct'])) {
            $query->setDistinct(true);
        }

        $this->applySearch($query, $options);
        $this->applyOrder($query, $options);

        return $query;
    }

    /**
     * Applies the scaffold search to the provided query
     * @param \ride\library\orm\query\ModelQuery $query
     * @param array $options
     * @return null
     */
    public function applySearch(ModelQuery $query, array $options = null) {
        // handle manual conditions
        if (isset($options['condition'])) {
            $conditions = $options['condition'];
            if (!is_array($conditions)) {
                $conditions = array($conditions);
            }

            foreach ($conditions as $condition) {
                if (!$condition) {
                    continue;
                }

                if (is_array($condition)) {
                    $variables = $condition;
                    $condition = array_shift($variables);
                } else {
                    $variables = array();
                }

                $query->addConditionWithVariables($condition, $variables);
            }
        }

        // handle filters
        $filter = isset($options['filter']) ? $options['filter'] : array();
        foreach ($filter as $fieldName => $filterValue) {
            if (!is_array($filterValue)) {
                $filterValue = array($filterValue);
            }

            $condition = '';

            $fieldTokens = explode('.', $fieldName);
            $field = $this->meta->getField($fieldTokens[0]);

            if (!$field instanceof HasField) {
                foreach ($filterValue as $index => $value) {
                    if ($value === 'null') {
                        $value = null;
                    }

                    if ($value === null) {
                        $condition .= ($condition ? ' OR ' : '') . '{' . $fieldName . '} IS NULL';
                    } else {
                        $condition .= ($condition ? ' OR ' : '') . '{' . $fieldName . '} = %' . $index . '%';
                    }
                }
            } else {
                foreach ($filterValue as $index => $value) {
                    if ($value === 'null') {
                        $value = null;
                    }

                    if ($value !== null) {
                        $condition .= ($condition ? ' OR ' : '') . '{' . $fieldName . '.id} = %' . $index . '%';
                    }
                }
            }

            $query->addConditionWithVariables($condition, $filterValue);
        }

        // handle match
        $conditions = array();
        $conditionArguments = array();
        $conditionArgumentIndex = 1;

        $match = isset($options['match']) ? $options['match'] : array();
        foreach ($match as $fieldName => $filterValue) {
            $fieldTokens = explode('.', $fieldName);
            $field = $this->meta->getField($fieldTokens[0]);

            if (!$field instanceof HasField) {
                if (!is_array($filterValue)) {
                    $filterValue = array($filterValue);
                }

                $condition = '';

                foreach ($filterValue as $index => $value) {
                    if ($field instanceof PropertyField  || count($fieldTokens) !== 1) {
                        $operator = 'LIKE';
                        $conditionArguments[$conditionArgumentIndex++] = '%' . $value . '%';
                    } else {
                        $operator = '=';
                        $conditionArguments[$conditionArgumentIndex++] = $value;
                    }

                    $conditions[] = '{' . $fieldName . '} ' . $operator . ' %' . ($conditionArgumentIndex - 1) . '%';
                }
            }
        }

        $queryString = isset($options['query']) ? $options['query'] : null;
        if ($queryString) {
            $conditionArguments[$conditionArgumentIndex++] = '%' . $queryString . '%';

            $fields = $this->meta->getFields();
            foreach ($fields as $fieldName => $field) {
                if (!$field->getOption('scaffold.search')) {
                    continue;
                }

                if ($field instanceof PropertyField) {
                    $conditions[] = '{' . $fieldName . '} LIKE  %' . ($conditionArgumentIndex - 1) . '%';
                } else {
                    $relationModel = $this->getRelationModel($fieldName);

                    $relationFields = $relationModel->getMeta()->getProperties();
                    foreach ($relationFields as $relationFieldName => $relationField) {
                        if (!$relationField->getOption('scaffold.search')) {
                            continue;
                        }

                        $conditions[] = '{' . $fieldName . '.' . $relationFieldName . '} LIKE  %' . ($conditionArgumentIndex - 1) . '%';
                    }
                }
            }
        }

        if ($conditions) {
            $query->addConditionWithVariables(implode(' OR ', $conditions), $conditionArguments);
        }
   }

    /**
     * Applies the scaffold order to the provided query
     * @param \ride\library\orm\query\ModelQuery $query
     * @param array $options
     * @return null
     */
    public function applyOrder(ModelQuery $query, array $options = null) {
        $order = null;
        if ($options !== null && array_key_exists('order', $options)) {
            $order = $options['order'];
        }

        if ($order && is_string($order)) {
            $query->addOrderBy($order);
        } else {
            $orderField = $order && array_key_exists('field', $order) ? $order['field'] : $this->findOrderField;
            $orderDirection = isset($order['direction']) ? $order['direction'] : $this->findOrderDirection;

            if ($orderField) {
                $fieldTokens = explode('.', $orderField);
                $field = $this->meta->getField($fieldTokens[0]);

                $query->addOrderBy('{' . $orderField . '} ' . $orderDirection);
            }
        }

        return $query;
    }

    /**
     * Saves an entry to the model
     * @param mixed $entry An entry of this model
     * @return null
     * @throws \Exception when the data could not be saved
     */
    protected function saveEntry($entry) {
        // $log = $this->orm->getLog();
        // $log->logDebug('save ' . $this->getName() . ' #' . $entry->getId());

        $state = $this->willSave($entry);
        if ($state === false) {
            return;
        }

        // $log->logDebug('validate ' . $this->getName());

        $this->validate($entry);

        $table = new TableExpression($this->meta->getName());

        if ($state === Entry::STATE_NEW) {
            $statement = new InsertStatement();

            $isNew = true;
            $isProxy = false;
            $loadedValues = array();
        } else {
            $id = $entry->getId();

            $this->saveStack[$id] = $id;

            $idField = new FieldExpression(ModelTable::PRIMARY_KEY, $table);
            $condition = new SimpleCondition($idField, new ScalarExpression($id), Condition::OPERATOR_EQUALS);

            $statement = new UpdateStatement();
            $statement->addCondition($condition);

            $isNew = false;
            $isProxy = $entry instanceof EntryProxy;
            if ($isProxy) {
                $loadedValues = $entry->getLoadedValues();
            } else {
                $loadedValues = array();
            }
        }

        foreach ($this->behaviours as $behaviour) {
            if ($isNew) {
                // $log->logDebug('pre insert ' . get_class($behaviour));
                $behaviour->preInsert($this, $entry);
            } else {
                // $log->logDebug('pre update ' . get_class($behaviour));
                $behaviour->preUpdate($this, $entry);
            }
        }

        if ($this->eventManager) {
            if ($isNew) {
                $this->eventManager->triggerEvent(self::EVENT_INSERT_PRE, array('model' => $this, 'entry' => $entry));
            } else {
                $this->eventManager->triggerEvent(self::EVENT_UPDATE_PRE, array('model' => $this, 'entry' => $entry));
            }
        }

        $statement->addTable($table);

        // $log->logDebug('properties');

        // add the properties to the statement
        $properties = $this->meta->getProperties();
        foreach ($properties as $fieldName => $field) {
            // insert with predefined primary key
            // if ($isNew && $fieldName == ModelTable::PRIMARY_KEY && $this->reflectionHelper->getProperty($entry, $fieldName)) {
            // } else
            if ($fieldName == ModelTable::PRIMARY_KEY || $field->isLocalized() || ($isProxy && !$entry->isFieldSet($fieldName))) {
                // don't add localized or unloaded proxy properties
                continue;
            }

            $value = $this->reflectionHelper->getProperty($entry, $fieldName);

            if ($isProxy && $entry->isValueLoaded($fieldName) && $entry->getLoadedValues($fieldName) === $value) {
                // don't add values which are the same as the current value
                continue;
            }

            $isSerialized = false;
            if ($field->getType() == 'serialize') {
                $value = serialize($value);

                $isSerialized = true;
            }

            $statement->addValue(new FieldExpression($fieldName), new ScalarExpression($value));

            if ($isSerialized) {
                $loadedValues[$fieldName] = unserialize($value);
            } else {
                $loadedValues[$fieldName] = $value;
            }
        }

        // $log->logDebug('belongsTo');

        // add the belongsTo relations to the statement
        $belongsTo = $this->meta->getBelongsTo();
        foreach ($belongsTo as $fieldName => $field) {
            if ($field->isLocalized() || ($isProxy && !$entry->isFieldSet($fieldName))) {
                // don't add localized or unloaded proxy relations
                continue;
            }

            $isValueLoaded = false;
            $value = $this->reflectionHelper->getProperty($entry, $fieldName);
            if ($isProxy && $entry->isValueLoaded($fieldName)) {
                $loadedValue = $entry->getLoadedValues($fieldName);
                if (($value === null && $loadedValue === null) || ($value && $loadedValue && $value->getId() === $loadedValue->getId() && $value->getEntryState() === Entry::STATE_CLEAN)) {
                    // don't add values which are the same as the current value
                    continue;
                }

                if ($loadedValue) {
                    $isValueLoaded = true;
                }
            }

            $foreignKey = $this->saveBelongsTo($value, $fieldName);

            if ($isValueLoaded && $foreignKey === $loadedValue->getId()) {
                // don't add values which are the same as the current value
                continue;
            }

            $statement->addValue(new FieldExpression($fieldName), new ScalarExpression($foreignKey));
            $loadedValues[$fieldName] = $value;
        }

        // $log->logDebug('statement');

        $fields = $statement->getValues();

        $executeStatement = !empty($fields);
        if ($isNew && !$executeStatement) {
            // make sure a new entry with only localized fields or has relations is inserted
            $statement->addValue(new FieldExpression(ModelTable::PRIMARY_KEY), new ScalarExpression(null));

            $executeStatement = true;
        }

        if ($executeStatement) {
            // executes the insert or update for the entry
            $connection = $this->orm->getConnection();
            $connection->executeStatement($statement);

            if ($isNew) {
                $id = $connection->getLastInsertId();

                $entry->setId($id);

                $this->saveStack[$id] = $id;
            }

            $this->clearCache();
        }

        // $log->logDebug('hasOne');

        // save the hasOne relations
        $hasOne = $this->meta->getHasOne();
        foreach ($hasOne as $fieldName => $field) {
            if ($field->isLocalized() || ($isProxy && !$entry->isFieldSet($fieldName))) {
                // don't add localized or unloaded proxy relations
                continue;
            }

            $value = $this->reflectionHelper->getProperty($entry, $fieldName);

            if ($isProxy && $entry->isValueLoaded($fieldName)) {
                $loadedValue = $entry->getLoadedValues($fieldName);
                if (($value === null && $loadedValue === null) || ($value && $loadedValue && $value->getId() === $loadedValue->getId() && $value->getEntryState() === Entry::STATE_CLEAN)) {
                    // don't process values which are the same as the current value
                    continue;
                }
            }

            $this->saveHasOne($value, $fieldName, $id);
            $loadedValues[$fieldName] = $value;
        }

        // $log->logDebug('hasMany');

        // save the hasMany relations
        $hasMany = $this->meta->getHasMany();
        foreach ($hasMany as $fieldName => $field) {
            if ($field->isLocalized() || ($isProxy && !$entry->isFieldSet($fieldName))) {
                // don't process localized or unloaded proxy relations
                continue;
            }

            $value = $this->reflectionHelper->getProperty($entry, $fieldName);

            if ($isProxy && $entry->isValueLoaded($fieldName)) {
                $loadedValue = $entry->getLoadedValues($fieldName);
                if (count($value) != count($loadedValue)) {
                    $isClean = false;
                } else {
                    $isClean = true;

                    foreach ($value as $valueEntry) {
                        $loadedValueEntry = array_shift($loadedValue);
                        if (!$loadedValueEntry || $valueEntry->getId() !== $loadedValueEntry->getId()) {
                            // not the entry we're looking for
                            $isClean = false;

                            break;
                        }

                        if ($valueEntry->getEntryState() !== Entry::STATE_CLEAN) {
                            // it's not clean, get out of the check
                            $isClean = false;

                            break;
                        }
                    }
                }

                if ($isClean) {
                    continue;
                }
            }

            $this->saveHasMany($value, $fieldName, $id, $isNew, $field->isDependant(), $field->isOrdered());
            $loadedValues[$fieldName] = $value;
        }

        if ($this->meta->isLocalized()) {
            // $log->logDebug('localized');
            $this->saveLocalizedEntry($entry, $isProxy, $isNew, $loadedValues);
        }

        // $log->logDebug('update state');

        if ($isProxy) {
            $entry->setLoadedValues($loadedValues);
        }
        $entry->setEntryState(Entry::STATE_CLEAN);

        unset($this->saveStack[$id]);

        foreach ($this->behaviours as $behaviour) {
            if ($isNew) {
                // $log->logDebug('post insert ' . get_class($behaviour));
                $behaviour->postInsert($this, $entry);
            } else {
                // $log->logDebug('post update ' . get_class($behaviour));
                $behaviour->postUpdate($this, $entry);
            }
        }

        if ($this->eventManager) {
            if ($isNew) {
                $this->eventManager->triggerEvent(self::EVENT_INSERT_POST, array('model' => $this, 'entry' => $entry));
            } else {
                $this->eventManager->triggerEvent(self::EVENT_UPDATE_POST, array('model' => $this, 'entry' => $entry));
            }
        }
    }

    /**
     * Checks if the provided entry needs a save
     * @param mixed $entry
     * @return boolean|integer False when there is no need for a save, the state
     * of the entry otherwise
     */
    protected function willSave($entry) {
        if (is_null($entry)) {
            return false;
        }

        $this->meta->isValidEntry($entry);

        if (isset($this->saveStack[$entry->getId()])) {
            return false;
        }

        $state = $entry->getEntryState();
        if ($state === Entry::STATE_CLEAN || $state === Entry::STATE_DELETED) {
            return false;
        }

        return $state;
    }

    /**
     * Saves the localized fields of the entry to the model
     * @param mixed $entry Entry instance
     * @param boolean $isProxy Flag to see if the entry is an entry proxy
     * @param boolean $isNew Flag to see if the entry is a new one
     * @param array $loadedValues Updated loaded values of the entry
     * @return null
     */
    private function saveLocalizedEntry($entry, $isProxy, $isNew, array &$loadedValues) {
        $entryLocale = null;
        if ($entry instanceof LocalizedEntry) {
            $entryLocale = $entry->getLocale();
        }

        $properties = array(
            LocalizedModel::FIELD_ENTRY => $this->createProxy($entry->getId(), $entryLocale, array(ModelTable::PRIMARY_KEY => $entry->getId())),
            LocalizedModel::FIELD_LOCALE => $this->getLocale($entryLocale),
        );

        $localizedModel = $this->getLocalizedModel();

        if ($isNew) {
            $localizedEntry = $localizedModel->createProxy(0, $entryLocale);

            $this->reflectionHelper->setProperty($localizedEntry, LocalizedModel::FIELD_ENTRY, $properties[LocalizedModel::FIELD_ENTRY]);
            $this->reflectionHelper->setProperty($localizedEntry, LocalizedModel::FIELD_LOCALE, $properties[LocalizedModel::FIELD_LOCALE]);
        } else {
            $localizedEntry = $localizedModel->createProxy(0, $entryLocale, $properties);

            if ($isProxy) {
                $localizedLoadedValues = $entry->getLoadedValues();

                $localizedLoadedValues[LocalizedModel::FIELD_ENTRY] = $properties[LocalizedModel::FIELD_ENTRY];
                $localizedLoadedValues[LocalizedModel::FIELD_LOCALE] = $properties[LocalizedModel::FIELD_LOCALE];

                unset($localizedLoadedValues[ModelTable::PRIMARY_KEY]);

                $localizedEntry->setLoadedValues($localizedLoadedValues);
            }
        }

        $fields = $this->meta->getLocalizedFields();
        foreach ($fields as $fieldName => $field) {
            if ($fieldName === ModelTable::PRIMARY_KEY || ($isProxy && !$entry->isValueLoaded($fieldName))) {
                unset($fields[$fieldName]);

                continue;
            }

            $this->reflectionHelper->setProperty($localizedEntry, $fieldName, $this->reflectionHelper->getProperty($entry, $fieldName));
        }

        $localizedModel->save($localizedEntry);

        foreach ($fields as $fieldName => $field) {
            if ($isProxy) {
                $loadedValues[$fieldName] = $localizedEntry->getLoadedValues($fieldName);
            }

            $this->reflectionHelper->setProperty($entry, $fieldName, $this->reflectionHelper->getProperty($localizedEntry, $fieldName));
        }
    }

    /**
     * Saves a belongs to value to the model of the field
     * @param mixed $relationEntry Value of the belongs to field
     * @param string $fieldName Name of the belongs to field
     * @return integer Foreign key for the belongs to field
     */
    private function saveBelongsTo($relationEntry, $fieldName) {
        if (empty($relationEntry)) {
            return null;
        }

        $relationModel = $this->getRelationModel($fieldName);
        $relationModel->save($relationEntry);

        return $relationEntry->getId();
    }

    /**
     * Saves a has one value to the model of the field
     * @param mixed $relationEntry Value of the has one field
     * @param string $fieldName Name of the has one field
     * @param integer $id Primary key of the data which is being saved
     * @return null
     */
    private function saveHasOne($relationEntry, $fieldName, $id) {
        if (is_null($relationEntry)) {
            return;
        }

        $relationForeignKey = $this->meta->getRelationForeignKey($fieldName);

        $linkModelName = $this->meta->getRelationLinkModelName($fieldName);
        if ($linkModelName) {
            // hasOne with a link model
            $linkModel = $this->getRelationLinkModel($fieldName);

            if (is_array($relationForeignKey)) {
                // relation to self

                // look for existing relation
                $conditions = array();
                foreach ($relationForeignKey as $foreignKey) {
                    $conditions[] = '{' . $foreignKey . '} = %1%';
                }

                $query = $linkModel->createQuery();
                $query->addCondition('(' . implode(' OR ', $conditions) . ')', $id);

                $link = $query->queryFirst();
                if ($link) {
                    // existing relation, change it
                    $foreignKey = each($relationForeignKey);
                    if ($this->reflectionHelper->getProperty($link, $foreignKey['value'])->getId() == $id) {
                        $foreignKey = each($relationForeignKey);
                    }

                    $this->reflectionHelper->setProperty($link, $foreignKey['value'], $this->createProxy($relationEntry->getId()));
                } else {
                    // no relation, create one
                    $link = $linkModel->createEntry();

                    $foreignKey = each($relationForeignKey);
                    $this->reflectionHelper->setProperty($link, $foreignKey['value'], $this->createProxy($id));

                    $foreignKey = each($relationForeignKey);
                    $this->reflectionHelper->setProperty($link, $foreignKey['value'], $this->createProxy($relationEntry->getId()));
                }
            } else {
                // relation with other model
                throw new OrmException('Not implemented');
            }

            $linkModel->save($link);
        } else {
            // hasOne as the other side of a belongsTo
            $relationModel = $this->getRelationModel($fieldName);

            $this->reflectionHelper->setProperty($relationEntry, $relationForeignKey, $this->createProxy($id));

            $relationModel->save($relationEntry);
        }
    }

    /**
     * Saves a has many value to the model of the field
     * @param array|null $relationEntries Value of the has many field
     * @param string $fieldName Name of the has many field
     * @param integer $id Primary key of the data which is being saved
     * @param boolean $isNew Flag to see if this is an insert or an update
     * @param boolean $isDependant Flag to see if the values of the field are
     * @param boolean $isOrdered Flag to see whether this is an ordered relation
     * dependant on this model
     * @return null
     */
    private function saveHasMany($relationEntries, $fieldName, $id, $isNew, $isDependant, $isOrdered) {
        if (is_null($relationEntries)) {
            return;
        }

        if (!is_array($relationEntries)) {
            throw new ModelException('Could not save the hasMany value of ' . $fieldName . ': provided value should be an array');
        }

        if ($isOrdered || $this->meta->isHasManyAndBelongsToMany($fieldName)) {
            $this->saveHasManyAndBelongsToMany($relationEntries, $fieldName, $id, $isNew, $isOrdered);
        } else {
            $this->saveHasManyAndBelongsTo($relationEntries, $fieldName, $id, $isNew, $isDependant);
        }
    }

    /**
     * Saves a has many value to the model of the field. This is a many to many field.
     * @param mixed $relationEntries Value of the has many field
     * @param string $fieldName Name of the has many field
     * @param integer $id Primary key of the data which is being saved
     * @param boolean $isNew Flag to see whether this is an insert or an update
     * @param boolean $isOrdered Flag to see whether this is an ordered relation
     * @return null
     */
    private function saveHasManyAndBelongsToMany($relationEntries, $fieldName, $id, $isNew, $isOrdered) {
        $foreignKeys = $this->meta->getRelationForeignKey($fieldName);

        $foreignKeyToSelf = null;
        if (!is_array($foreignKeys)) {
            // relation to other model
            $foreignKeyToSelf = $this->meta->getRelationForeignKeyToSelf($fieldName);
            $foreignKeys = array($foreignKeys, $foreignKeyToSelf);
            $isRelationToSelf = false;
        } else {
            // relation to self
            $field = $this->meta->getField($fieldName);
            $foreignKeyToSelf = $field->getForeignKeyName();

            $foreignKeys = array_values($foreignKeys);

            // force the defined foreign key to the back
            foreach ($foreignKeys as $foreignKeyIndex => $foreignKey) {
                if ($foreignKey == $foreignKeyToSelf) {
                    unset($foreignKeys[$foreignKeyIndex]);
                    $foreignKeys[] = $foreignKeyToSelf;

                    break;
                }
            }

            $foreignKeys = array_values($foreignKeys);
            $isRelationToSelf = true;
        }

        $relationModel = $this->getRelationModel($fieldName);
        $linkModel = $this->getRelationLinkModel($fieldName);
        $oldHasMany = null;

        if (!$isNew) {
            if ($foreignKeyToSelf) {
                // relation with other model or one-direction with self
                $oldHasMany = $this->findOldHasManyAndBelongsToMany($linkModel, $id, $foreignKeyToSelf, $foreignKeys[0]);
            } else {
                // relation with self
                $oldHasMany = $this->findOldHasManyAndBelongsToMany($linkModel, $id, $foreignKeys);
            }
        }

        $linkTable = new TableExpression($linkModel->getName());
        $foreignKey1 = new FieldExpression($foreignKeys[0]);
        $foreignKey2 = new FieldExpression($foreignKeys[1]);

        if ($isOrdered) {
            if ($isRelationToSelf) {
                $weightFieldName = substr($foreignKeys[0], 0, -1);
            } else {
                $weightFieldName = $foreignKeys[0];
            }

            $weightField = $weightFieldName . 'Weight';
        }

        $weight = 0;
        foreach ($relationEntries as $relationEntry) {
            $weight++;

            $relationModel->save($relationEntry);

            $relationEntryId = $relationEntry->getId();

            if (isset($oldHasMany[$relationEntryId])) {
                if (!$isOrdered || ($isOrdered && $oldHasMany[$relationEntryId][$weightField] == $weight)) {
                    unset($oldHasMany[$relationEntryId]);

                    continue;
                } else {
                    $condition = new SimpleCondition(new FieldExpression(ModelTable::PRIMARY_KEY), new ScalarExpression($oldHasMany[$relationEntryId][ModelTable::PRIMARY_KEY]), Condition::OPERATOR_EQUALS);

                    $statement = new UpdateStatement();
                    $statement->addTable($linkTable);
                    $statement->addValue(new FieldExpression($weightField), new ScalarExpression($weight));
                    $statement->addCondition($condition);

                    $this->executeStatement($statement);

                    unset($oldHasMany[$relationEntryId]);
                }
            } else {
                $statement = new InsertStatement();
                $statement->addTable($linkTable);
                $statement->addValue($foreignKey1, $relationEntryId);
                $statement->addValue($foreignKey2, $id);

                if ($isOrdered) {
                    $statement->addValue(new FieldExpression($weightField), new ScalarExpression($weight));
                }

                $this->executeStatement($statement);
            }

            $linkModel->clearCache();
        }

        if (!$isNew && $oldHasMany) {
            $this->deleteOldHasManyAndBelongsToMany($linkModel, $oldHasMany);
        }
    }

    /**
     * Gets the primary keys of the has many to many values
     * @param Model $linkModel Link model of the has many field
     * @param integer $id Primary key of the entry
     * @param string|array $foreignKeyToSelf Name of the foreign key(s) to this
     * model
     * @param string|null $foreignKey Name of the foreign key to the relation
     * model
     * @return array Array with the primary key of the relation entry as key and
     * the primary key in the link model as value
     */
    private function findOldHasManyAndBelongsToMany($linkModel, $id, $foreignKeyToSelf, $foreignKey = null) {
        $statement = new SelectStatement();
        $statement->addTable(new TableExpression($linkModel->getName()));
        // $statement->addField(new FieldExpression(ModelTable::PRIMARY_KEY));

        if (is_array($foreignKeyToSelf)) {
            $statement->setOperator(Condition::OPERATOR_OR);

            foreach ($foreignKeyToSelf as $foreignKey) {
                $condition = new SimpleCondition(new FieldExpression($foreignKey), new ScalarExpression($id), Condition::OPERATOR_EQUALS);

                // $statement->addField(new FieldExpression($foreignKey));
                $statement->addCondition($condition);
            }

            $isRelationWithSelf = true;
        } else {
            $condition = new SimpleCondition(new FieldExpression($foreignKeyToSelf), new ScalarExpression($id), Condition::OPERATOR_EQUALS);

            // $statement->addField(new FieldExpression($foreignKeyToSelf));
            // $statement->addField(new FieldExpression($foreignKey));
            $statement->addCondition($condition);

            $isRelationWithSelf = false;
        }

        $result = $this->executeStatement($statement);

        $oldHasMany = array();

        if ($isRelationWithSelf) {
            foreach ($result as $record) {
                if ($record[$foreignKeyToSelf[0]] == $id) {
                    $foreignKeyIndex = 1;
                } else {
                    $foreignKeyIndex = 0;
                }

                $oldHasMany[$record[$foreignKeyToSelf[$foreignKeyIndex]]] = $record;
            }
        } else {
            foreach ($result as $record) {
                $oldHasMany[$record[$foreignKey]] = $record;
            }
        }

        return $oldHasMany;
    }

    /**
     * Deletes the old has many values which are not saved
     * @param Model $relationModel Model of the has many field
     * @param array $oldHasMany Array with the primary key of the relation entry
     * as key and the primary key in the link model as value
     * @return null
     */
    private function deleteOldHasManyAndBelongsToMany($linkModel, $oldHasMany) {
        foreach ($oldHasMany as $record) {
            $linkEntry = $linkModel->createProxy($record[ModelTable::PRIMARY_KEY]);

            $linkModel->delete($linkEntry);
        }
    }

    /**
     * Saves a has many value to the model of the field. This is a many to one
     * field.
     * @param mixed $relationEntries Value of the has many field
     * @param string $fieldName Name of the has many field
     * @param integer $id Primary key of the data which is being saved
     * @param boolean $isNew Flag to see whether this is an insert or an update
     * @param boolean $isDependant Flag to see if the values of the field are
     * dependant on this model
     * @return null
     */
    private function saveHasManyAndBelongsTo($relationEntries, $fieldName, $id, $isNew, $isDependant) {
        $relationModel = $this->getRelationModel($fieldName);

        $foreignKey = $this->meta->getRelationForeignKey($fieldName);

        if (!$isNew) {
            $oldHasMany = $this->findOldHasManyAndBelongsTo($relationModel, $foreignKey, $id);
        }

        foreach ($relationEntries as $relationEntry) {
            $this->reflectionHelper->setProperty($relationEntry, $foreignKey, $this->createProxy($id));

            if (!($relationEntry instanceof EntryProxy && $relationEntry->getEntryState() === Entry::STATE_CLEAN)) {
                $relationModel->save($relationEntry);
            }

            $relationEntryId = $this->reflectionHelper->getProperty($relationEntry, ModelTable::PRIMARY_KEY);
            if (!$isNew && array_key_exists($relationEntryId, $oldHasMany)) {
                unset($oldHasMany[$relationEntryId]);
            }
        }

        if (!$isNew && $oldHasMany) {
            $this->deleteOldHasManyAndBelongsTo($relationModel, $foreignKey, $oldHasMany, $isDependant);
        }
    }

    /**
     * Gets the primary keys of the has many values
     * @param Model $relationModel Model of the has many field
     * @param string $foreignKey Name of the foreign key to this model
     * @param integer $id Value for the foreign key
     * @return array Array with the primary key of the has many value as key and value
     */
    private function findOldHasManyAndBelongsTo($relationModel, $foreignKey, $id) {
        $condition = new SimpleCondition(new FieldExpression($foreignKey), new ScalarExpression($id), Condition::OPERATOR_EQUALS);

        $statement = new SelectStatement();
        $statement->addTable(new TableExpression($relationModel->getName()));
        $statement->addField(new FieldExpression(ModelTable::PRIMARY_KEY));
        $statement->addCondition($condition);

        $result = $this->executeStatement($statement);

        $oldHasMany = array();
        foreach ($result as $record) {
            $oldHasMany[$record[ModelTable::PRIMARY_KEY]] = $record[ModelTable::PRIMARY_KEY];
        }

        return $oldHasMany;
    }

    /**
     * Deletes the old has many values which are not saved
     * @param Model $relationModel Model of the has many field
     * @param string $foreignKey Name of the foreign key to this model
     * @param array $oldHasMany Array with the primary key of the has many value as key and value
     * @param boolean $idDependant Flag to see whether the has many value is dependant on this model
     * @return null
     */
    private function deleteOldHasManyAndBelongsTo($relationModel, $foreignKey, $oldHasMany, $isDependant) {
        if ($isDependant) {
            $relationModel->delete($oldHasMany);

            return;
        }

        foreach ($oldHasMany as $id) {
            $relationEntry = $relationModel->createProxy($id);

            $this->reflectionHelper->setProperty($relationEntry, $foreignKey, null);

            $relationModel->save($relationEntry);
        }
    }

    /**
     * Deletes data from this model
     * @param mixed $entry Primary key of the data or a data object of this model
     * @return null
     */
    protected function deleteEntry($entry) {
        $id = $this->getPrimaryKey($entry);

        $query = $this->createQuery();
        $query->setIncludeUnlocalized(true);
        $query->addCondition('{id} = %1%', $id);

        $entry = $query->queryFirst();
        if (!$entry) {
            return;
        }

        // check for delete blocking
        if ($this->meta->willBlockDeleteWhenUsed() && $this->isDataReferencedInUnlinkedModels($entry)) {
            $format = $this->meta->getFormat('title');
            $entryFormatter = $this->orm->getEntryFormatter();

            $validationError = new ValidationError('orm.error.data.used', '%data% is still in use by another record', array('data' => $entryFormatter->formatEntry($entry, $format)));

            $validationException = new ValidationException();
            $validationException->addErrors('id', array($validationError));

            throw $validationException;
        }

        // handle pre delete behaviour
        foreach ($this->behaviours as $behaviour) {
            $behaviour->preDelete($this, $entry);
        }
        if ($this->eventManager) {
            $this->eventManager->triggerEvent(self::EVENT_DELETE_PRE, array('model' => $this, 'entry' => $entry));
        }

        if ($this->meta->isLocalized()) {
            $this->getLocalizedModel()->deleteEntryLocalization($id);
        }

        $this->deleteDataInUnlinkedModels($entry);

        $condition = new SimpleCondition(new FieldExpression(ModelTable::PRIMARY_KEY), new SqlExpression($id), Condition::OPERATOR_EQUALS);

        $statement = new DeleteStatement();
        $statement->addTable(new TableExpression($this->getName()));
        $statement->addCondition($condition);

        $this->executeStatement($statement);

        $this->clearCache();

        // delete the related entries
        $belongsTo = $this->meta->getBelongsTo();
        foreach ($belongsTo as $fieldName => $field) {
            $this->deleteBelongsTo($fieldName, $field, $entry);
        }

        $hasOne = $this->meta->getHasOne();
        foreach ($hasOne as $fieldName => $field) {
            $this->deleteBelongsTo($fieldName, $field, $entry);
        }

        $hasMany = $this->meta->getHasMany();
        foreach ($hasMany as $fieldName => $field) {
            $this->deleteHasMany($fieldName, $field, $entry);
        }

        // updates the state of the entry
        $entry->setEntryState(Entry::STATE_DELETED);

        // handle post delete behaviour
        foreach ($this->behaviours as $behaviour) {
            $behaviour->postDelete($this, $entry);
        }
        if ($this->eventManager) {
            $this->eventManager->triggerEvent(self::EVENT_DELETE_POST, array('model' => $this, 'entry' => $entry));
        }

        // go back
        return $entry;
    }

    /**
     * Deletes a localized entry from this model.
     * If the requested locale is the only version of the entry, the full entry
     * will be deleted
     * @param mixed $entry Entry of this model
     * @param string $locale, the current locale
     * @return null
     */
    protected function deleteLocalizedEntry($entry, $locale) {
        if ($entry == null) {
            return null;
        }

        // entry and locale check
        $id = $this->getPrimaryKey($entry);
        $locale = $this->getLocale($locale);

        // fetch localized entry state
        $localizedModel = $this->getLocalizedModel();
        $localizedEntryIds = $localizedModel->getLocalizedIds($id);

        // remove necessairy data
        if (!isset($localizedEntryIds[$locale])) {
            return null;
        } elseif (count($localizedEntryIds) > 1) {
            return $localizedModel->delete($localizedEntryIds[$locale]);
        } else {
            return $this->delete($entry);
        }
    }

    /**
     * Deletes the value of the provided relation field in the provided data. This will only be done if the
     * field is dependant.
     * @param string $fieldName Name of the relation field
     * @param \ride\library\orm\definition\field\RelationField $field Definition of the relation field
     * @param mixed $entry Data obiect
     * @return null
     */
    private function deleteBelongsTo($fieldName, RelationField $field, $entry) {
        if ($field->isDependant() && $entry->$fieldName) {
            $model = $this->getRelationModel($fieldName);
            $model->delete($entry->$fieldName);
        }
    }

    /**
     * Deletes or clears the values of the provided has many field in the provided data.
     * @param string $fieldName Name of the has many field
     * @param \ride\library\orm\definition\field\HasManyField $field Definition of the has many field
     * @param mixed $entry Data obiect
     * @return null
     */
    private function deleteHasMany($fieldName, HasManyField $field, $entry) {
        $model = $this->getRelationModel($fieldName);

        if (!$this->meta->isHasManyAndBelongsToMany($fieldName)) {
            if ($field->isDependant()) {
                if ($entry->$fieldName) {
                    foreach ($entry->$fieldName as $record) {
                        $model->delete($record);
                    }
                }
            } else {
                $this->clearHasMany($this->getRelationModelTable($fieldName), $entry->id, true);
            }
            return;
        }

        $linkModelTable = $this->getRelationLinkModelTable($fieldName);

        if ($field->isDependant()) {
            foreach ($entry->$fieldName as $record) {
                $model->delete($record);
            }

            $keepRecord = false;
        } else {
            $fields = $linkModelTable->getFields();
            if ($field->isOrdered()) {
                if ($field->getRelationModelName() == $this->getName()) {
                    // id, model entry 1, model entry 2, model order
                    $keepRecord = count($fields) != 4;
                } else {
                    // id, model entry, relation entry, model order, relation order
                    $keepRecord = count($fields) != 5;
                }
            } else {
                // id, model entry, relation entry
                $keepRecord = count($fields) != 3;
            }
        }

        $this->clearHasMany($linkModelTable, $entry->id, $keepRecord);
    }

    /**
     * Deletes or clears, depending on the keepRecord argument, the values of the provided table
     * which have a relation with the provided data
     * @param \ride\library\orm\definition\ModelTable $modelTable Table definition of the model of the has many relation
     * @param integer $id Primary key of the data
     * @param boolean $keepRecord True to clear the link, false to delete the link
     * @return null
     */
    private function clearHasMany(ModelTable $modelTable, $id, $keepRecord) {
        $table = new TableExpression($modelTable->getName());

        $relationFields = $modelTable->getRelationFields($this->getName());
        $fields = $relationFields[ModelTable::BELONGS_TO];
        foreach ($fields as $field) {
            $fieldName = $field->getName();

            if ($keepRecord) {
                $statement = new UpdateStatement();
                $statement->addValue(new FieldExpression($fieldName), null);
            } else {
                $statement = new DeleteStatement();
            }

            $condition = new SimpleCondition(new FieldExpression($fieldName), new SqlExpression($id), Condition::OPERATOR_EQUALS);

            $statement->addTable($table);
            $statement->addCondition($condition);

            $this->executeStatement($statement);
        }

        $model = $this->getModel($modelTable->getName());
        $model->clearCache();
    }

    /**
     * Checks if the provided data is referenced in another model
     * @param mixed $entry Data to check for references
     * @return null
     * @throws \ride\library\validation\exception\ValidationException when the data is referenced in another model
     */
    protected function isDataReferencedInUnlinkedModels($entry) {
        $foundReference = false;

        $unlinkedModels = $this->meta->getUnlinkedModels();
        foreach ($unlinkedModels as $modelName) {
            $foundReference = $this->isDataReferencedInModel($modelName, $entry);
            if ($foundReference) {
                break;
            }
        }

        return $foundReference;
    }

    /**
     * Checks whether the provided data is references in the provided model
     * @param string $modelName Name of the model to check for references
     * @param mixed $entry Data object to check for
     * @return boolean True if the provided model references the provided data, false otherwise
     */
    private function isDataReferencedInModel($modelName, $entry) {
        $model = $this->getModel($modelName);
        $meta = $model->getMeta();
        $belongsTo = $meta->getBelongsTo();

        $fields = array();
        foreach ($belongsTo as $field) {
            if ($field->getRelationModelName() == $this->getName()) {
                $fields[] = $field->getName();
            }
        }

        if (!$fields) {
            return false;
        }

        $query = $model->createQuery();
        $query->setOperator('OR');
        foreach ($fields as $fieldName) {
            $query->addCondition('{' . $fieldName . '} = %1%', $entry->id);
        }

        return $query->count() ? true : false;
    }

    /**
     * Deletes or clears the data in models which use this model but are not linked from this model
     * @param mixed $entry Data object
     * @return null
     */
    private function deleteDataInUnlinkedModels($entry) {
        $unlinkedModels = $this->meta->getUnlinkedModels();

        foreach ($unlinkedModels as $unlinkedModelName) {
            $this->deleteDataInModel($unlinkedModelName, $entry);
        }
    }

    /**
     * Deletes or clears the data in the provided model which has links with the provided data
     * @param string $unlinkedModelName Name of the model which has links with this model but which are not linked from this model
     * @param mixed $entry Data object
     * @return null
     */
    private function deleteDataInModel($unlinkedModelName, $entry) {
        $model = $this->getModel($unlinkedModelName);
        $meta = $model->getMeta();
        $belongsTo = $meta->getBelongsTo();

        $fields = array();
        foreach ($belongsTo as $field) {
            if ($field->getRelationModelName() == $this->getName()) {
                $fields[] = $field->getName();
            }
        }

        if (!$fields) {
            return;
        }

        $properties = $meta->getProperties();
        foreach ($properties as $propertyName => $property) {
            if ($propertyName == ModelTable::PRIMARY_KEY || strpos($propertyName, 'Weight')) {
                unset($properties[$propertyName]);
            }
        }

        $deleteData = false;
        if (!count($properties)) {
            if (count($belongsTo) == 2) {
                $deleteData = true;
            }
        }

        $table = new TableExpression($unlinkedModelName);
        $localizedTable = new TableExpression($unlinkedModelName . ModelMeta::SUFFIX_LOCALIZED);
        $id = new SqlExpression($entry->id);

        if ($deleteData) {
            foreach ($fields as $fieldName) {
                $condition = new SimpleCondition(new FieldExpression($fieldName), $id, Condition::OPERATOR_EQUALS);

                $statement = new DeleteStatement();
                $statement->addTable($table);
                $statement->addCondition($condition);

                $this->executeStatement($statement);
            }
        } else {
            foreach ($fields as $fieldName) {
                $field = new FieldExpression($fieldName);

                $condition = new SimpleCondition($field, $id, Condition::OPERATOR_EQUALS);

                $statement = new UpdateStatement();
                if ($belongsTo[$fieldName]->isLocalized()) {
                    $statement->addTable($localizedTable);
                } else {
                    $statement->addTable($table);
                }
                $statement->addValue($field, null);
                $statement->addCondition($condition);

                $this->executeStatement($statement);
            }
        }

        $model->clearCache();
    }

}
