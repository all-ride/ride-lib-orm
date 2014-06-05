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
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasField;
use ride\library\orm\definition\field\HasOneField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\entry\proxy\EntryProxy;
use ride\library\orm\entry\LocalizedEntry;
use ride\library\orm\exception\ModelException;
use ride\library\orm\model\data\format\DataFormatter;
use ride\library\orm\model\data\DataFactory;
use ride\library\orm\model\data\DataValidator;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\ValidationError;

use \Exception;

/**
 * Basic implementation of a data model
 */
class GenericModel extends AbstractModel {

    /**
     * Stack with the primary keys of the data which is saved, to skip save loops
     * @var array
     */
    private $saveStack;

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
     * @return array
     */
    public function find(array $options = null, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0) {
        $query = $this->createFindQuery($options, $locale, $fetchUnlocalized, $recursiveDepth);

        if (isset($options['limit'])) {
            $page = isset($options['page']) ? $options['page'] : 1;

            $query->setLimit($options['limit'], $options['limit'] * ($page - 1));
        }

        return $query->query();
    }

    /**
     * Gets the find query for this model
     * @param array $options Options for the query
     * <ul>
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

        $filter = isset($options['filter']) ? $options['filter'] : array();
        foreach ($filter as $fieldName => $filterValue) {
            $fieldTokens = explode('.', $fieldName);
            $field = $this->meta->getField($fieldTokens[0]);

            if (!$field instanceof HasField) {
                if (!is_array($filterValue)) {
                    $filterValue = array($filterValue);
                }

                $condition = '';
                foreach ($filterValue as $index => $value) {
                    $condition .= ($condition ? ' OR ' : '') . '{' . $fieldName . '} = %' . $index . '%';
                }

                $query->addConditionWithVariables($condition, $filterValue);
            }

        }

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
                    if ($field instanceof PropertyField) {
                        $operator = 'LIKE';
                        $filterValue[$index] = '%' . $value . '%';
                    } else {
                        $operator = '=';
                    }

                    $condition .= ($condition ? ' OR ' : '') . '{' . $fieldName . '} ' . $operator . ' %' . $index . '%';
                }

                $query->addConditionWithVariables($condition, $filterValue);
            }
        }

        $orderField = isset($options['order']['field']) ? $options['order']['field'] : $this->findOrderField;
        $orderDirection = isset($options['order']['direction']) ? $options['order']['direction'] : $this->findOrderDirection;
        if ($orderField) {
            $fieldTokens = explode('.', $orderField);
            $field = $this->meta->getField($fieldTokens[0]);

            $query->addOrderBy('{' . $orderField . '} ' . $orderDirection);
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
        if (is_null($entry)) {
            return $entry;
        }

        $this->meta->isValidEntry($entry);

        $id = $this->reflectionHelper->getProperty($entry, ModelTable::PRIMARY_KEY);
        if (isset($this->saveStack[$id])) {
            return;
        }

        $isProxy = $entry instanceof EntryProxy;
        if ($isProxy && $entry->hasCleanState()) {
            return;
        }

        $this->validate($entry);

        $table = new TableExpression($this->meta->getName());
        $idField = new FieldExpression(ModelTable::PRIMARY_KEY, $table);

        if (!$id) {
            $statement = new InsertStatement();

            $isNew = true;
        } else {
            $this->saveStack[$id] = $id;

            $condition = new SimpleCondition($idField, new ScalarExpression($id), Condition::OPERATOR_EQUALS);

            $statement = new UpdateStatement();
            $statement->addCondition($condition);

            $isNew = false;
        }

        foreach ($this->behaviours as $behaviour) {
            if ($isNew) {
                $behaviour->preInsert($this, $entry);
            } else {
                $behaviour->preUpdate($this, $entry);
            }
        }

        $newState = array();

        $statement->addTable($table);

        $properties = $this->meta->getProperties();
        foreach ($properties as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY || $field->isLocalized() || ($isProxy && !$entry->isFieldLoaded($fieldName))) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($entry, $fieldName);

            if ($isProxy && $entry->hasFieldState($fieldName) && $entry->getFieldState($fieldName) === $value) {
                continue;
            }

            if ($field->getType() == 'serialize') {
                $value = serialize($value);
            }

            $statement->addValue(new FieldExpression($fieldName), new ScalarExpression($value));
            $newState[$fieldName] = $value;
        }

        $belongsTo = $this->meta->getBelongsTo();
        foreach ($belongsTo as $fieldName => $field) {
            if ($field->isLocalized() || ($isProxy && !$entry->isFieldLoaded($fieldName))) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($entry, $fieldName);

            if ($isProxy && $entry->hasFieldState($fieldName)) {
                $fieldState = $entry->getFieldState($fieldName);
                if ($fieldState === $value) {
                    continue;
                }

                $hasFieldState = true;
            } else {
                $hasFieldState = false;
            }

            $foreignKey = $this->saveBelongsTo($value, $fieldName);

            if ($hasFieldState && $foreignKey === $this->reflectionHelper->getProperty($fieldState, ModelTable::PRIMARY_KEY)) {
                continue;
            }

            $statement->addValue(new FieldExpression($fieldName), new ScalarExpression($foreignKey));
            $newState[$fieldName] = $value;
        }

        $fields = $statement->getValues();

        $executeStatement = !empty($fields);
        if (!$executeStatement && $isNew && $this->meta->isLocalized()) {
            $statement->addValue(new FieldExpression(ModelTable::PRIMARY_KEY), new ScalarExpression(0));

            $executeStatement = true;
        }

        if ($executeStatement) {
            $connection = $this->orm->getConnection();
            $connection->executeStatement($statement);

            if ($isNew) {
                $id = $connection->getLastInsertId();

                $entry->setId($id);
            }

            $this->clearCache();
        }

        $hasOne = $this->meta->getHasOne();
        foreach ($hasOne as $fieldName => $field) {
            if ($field->isLocalized() || ($isProxy && !$entry->isFieldLoaded($fieldName))) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($entry, $fieldName);

            if ($isProxy && $entry->hasFieldState($fieldName) && $entry->getFieldState($fieldName) === $value) {
                continue;
            }

            $this->saveHasOne($value, $fieldName, $id);
            $newState[$fieldName] = $value;
        }

        $hasMany = $this->meta->getHasMany();
        foreach ($hasMany as $fieldName => $field) {
            if ($field->isLocalized() || ($isProxy && !$entry->isFieldLoaded($fieldName))) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($entry, $fieldName);

            if ($isProxy && $entry->hasFieldState($fieldName) && $entry->getFieldState($fieldName) === $value) {
                continue;
            }

            $this->saveHasMany($value, $fieldName, $id, $isNew, $field->isDependant());
            $newState[$fieldName] = $value;
        }

        if ($this->meta->isLocalized()) {
            $this->saveLocalizedEntry($entry, $isProxy, $newState);
        }

        foreach ($this->behaviours as $behaviour) {
            if ($isNew) {
                $behaviour->postInsert($this, $entry);
            } else {
                $behaviour->postUpdate($this, $entry);
            }
        }

        if ($isProxy) {
            $newState = $this->getResultParser()->processState($newState);
            foreach ($newState as $fieldName => $value) {
                $entry->setFieldState($fieldName, $value);
            }
        }

        unset($this->saveStack[$id]);
    }

    /**
     * Saves the localized fields of the entry to the model
     * @param mixed $entry Entry instance
     * @param boolean $isProxy Flag to see if the entry is an entry proxy
     * @param array $newState Updated state of the entry
     * @return null
     */
    private function saveLocalizedEntry($entry, $isProxy, array &$newState) {
        $entryLocale = null;
        if ($entry instanceof LocalizedEntry) {
            $entryLocale = $entry->getLocale();
        }

        $properties = array(
            LocalizedModel::FIELD_ENTRY => $this->createProxy($entry->getId(), $entryLocale),
            LocalizedModel::FIELD_LOCALE => $this->getLocale($entryLocale),
        );

        $localizedModel = $this->getLocalizedModel();
        $localizedEntry = $localizedModel->createProxy(0, $entryLocale, $properties);

        if ($isProxy) {
            $localizedEntry->setEntryState($entry->getEntryState());
        }

        $fields = $this->meta->getLocalizedFields();
        foreach ($fields as $fieldName => $field) {
            if ($isProxy && !$entry->isFieldLoaded($fieldName)) {
                continue;
            }

            $this->reflectionHelper->setProperty($localizedEntry, $fieldName, $this->reflectionHelper->getProperty($entry, $fieldName));
        }

        $localizedModel->save($localizedEntry);

        foreach ($fields as $fieldName => $field) {
            if ($isProxy) {
                if (!$entry->isFieldLoaded($fieldName)) {
                    continue;
                }

                $newState[$fieldName] = $localizedEntry->getFieldState($fieldName);
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

        if (is_numeric($relationEntry)) {
            if ($relationEntry == 0) {
                return null;
            }

            return $relationEntry;
        }

        $relationModel = $this->getRelationModel($fieldName);
        $relationModel->save($relationEntry);

        return $this->reflectionHelper->getProperty($relationEntry, ModelTable::PRIMARY_KEY);
    }

    /**
     * Saves a has one value to the model of the field
     * @param mixed $relationEntry Value of the has one field
     * @param string $fieldName Name of the has one field
     * @param integer $id Primary key of the data which is being saved
     * @return null
     */
    private function saveHasOne($relationEntry, $fieldName, $id) {
        if (is_null($entry)) {
            return;
        }

        $relationModel = $this->meta->getRelationModel($fieldName);
        $relationForeignKey = $this->meta->getRelationForeignKey($fieldName);

        $this->reflectionHelper->setProperty($relationEntry, $relationForeignKey, $this->createProxy($id));

        $relationModel->save($relationEntry);
    }

    /**
     * Saves a has many value to the model of the field
     * @param array|null $relationEntries Value of the has many field
     * @param string $fieldName Name of the has many field
     * @param integer $id Primary key of the data which is being saved
     * @param boolean $isNew Flag to see if this is an insert or an update
     * @param boolean $isDependant Flag to see if the values of the field are
     * dependant on this model
     * @return null
     */
    private function saveHasMany($relationEntries, $fieldName, $id, $isNew, $isDependant) {
        if (is_null($relationEntries)) {
            return;
        }

        if (!is_array($relationEntries)) {
            throw new ModelException('Provided value for ' . $fieldName . ' should be an array');
        }

        if ($this->meta->isHasManyAndBelongsToMany($fieldName)) {
            $this->saveHasManyAndBelongsToMany($relationEntries, $fieldName, $id, $isNew);
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
     * @return null
     */
    private function saveHasManyAndBelongsToMany($relationEntries, $fieldName, $id, $isNew) {
        $foreignKeys = $this->meta->getRelationForeignKey($fieldName);

        $foreignKeyToSelf = null;
        if (!is_array($foreignKeys)) {
            $foreignKeyToSelf = $this->meta->getRelationForeignKeyToSelf($fieldName);
            $foreignKeys = array($foreignKeys, $foreignKeyToSelf);
        } else {
            $foreignKeys = array_values($foreignKeys);
        }

        $relationModel = $this->getRelationModel($fieldName);
        $linkModel = $this->getRelationLinkModel($fieldName);

        if (!$isNew) {
            if ($foreignKeyToSelf) {
                // relation with other model
                $oldHasMany = $this->findOldHasManyAndBelongsToMany($linkModel, $id, $foreignKeyToSelf, $foreignKeys[0]);
            } else {
                // relation with self
                $oldHasMany = $this->findOldHasManyAndBelongsToMany($linkModel, $id, $foreignKeys);
            }
        }

        $linkTable = new TableExpression($linkModel->getName());
        $foreignKey1 = new FieldExpression($foreignKeys[0]);
        $foreignKey2 = new FieldExpression($foreignKeys[1]);

        foreach ($relationEntries as $relationEntry) {
            if (!is_numeric($relationEntry)) {
                $relationModel->save($relationEntry);

                $relationEntryId = $this->reflectionHelper->getProperty($relationEntry, ModelTable::PRIMARY_KEY);
            } else {
                $relationEntryId = $relationEntry;
            }

            if (isset($oldHasMany[$relationEntryId])) {
                unset($oldHasMany[$relationEntryId]);

                continue;
            }

            $statement = new InsertStatement();
            $statement->addTable($linkTable);
            $statement->addValue($foreignKey1, $relationEntryId);
            $statement->addValue($foreignKey2, $id);

            $this->executeStatement($statement);

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
        $statement->addField(new FieldExpression(ModelTable::PRIMARY_KEY));

        if (is_array($foreignKeyToSelf)) {
            $statement->setOperator(Condition::OPERATOR_OR);

            foreach ($foreignKeyToSelf as $foreignKey) {
                $condition = new SimpleCondition(new FieldExpression($foreignKey), new ScalarExpression($id), Condition::OPERATOR_EQUALS);

                $statement->addField(new FieldExpression($foreignKey));
                $statement->addCondition($condition);
            }

            $isRelationWithSelf = true;
        } else {
            $condition = new SimpleCondition(new FieldExpression($foreignKeyToSelf), new ScalarExpression($id), Condition::OPERATOR_EQUALS);

            $statement->addField(new FieldExpression($foreignKeyToSelf));
            $statement->addField(new FieldExpression($foreignKey));
            $statement->addCondition($condition);

            $isRelationWithSelf = false;
        }

        $result = $this->executeStatement($statement);

        $oldHasMany = array();

        if ($isRelationWithSelf) {
            foreach ($result as $record) {
                if ($record[$foreignKeyToSelf[0]] == $id) {
                    $oldHasMany[$record[$foreignKeyToSelf[1]]] = $record[ModelTable::PRIMARY_KEY];
                } else {
                    $oldHasMany[$record[$foreignKeyToSelf[0]]] = $record[ModelTable::PRIMARY_KEY];
                }
            }
        } else {
            foreach ($result as $record) {
                $oldHasMany[$record[$foreignKey]] = $record[ModelTable::PRIMARY_KEY];
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
        foreach ($oldHasMany as $id) {
            $linkEntry = $linkModel->createProxy($id);
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
            if (is_numeric($relationEntry)) {
                if (!$isNew && array_key_exists($relationEntry, $oldHasMany)) {
                    unset($oldHasMany[$record]);
                }

                continue;
            }

            $skipSave = false;
            if ($relationEntry instanceof EntryProxy && $relationEntry->hasCleanState()) {
                $skipSave = true;
            }

            if (!$skipSave) {
                $this->reflectionHelper->setProperty($relationEntry, $foreignKey, $this->createProxy($id));

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

        if ($entry == null) {
            return;
        }

        if ($this->meta->willBlockDeleteWhenUsed() && $this->isDataReferencedInUnlinkedModels($entry)) {
            $validationError = new ValidationError('orm.error.data.used', '%data% is still in use by another record', array('data' => $this->meta->formatData($entry)));

            $validationException = new ValidationException();
            $validationException->addErrors('id', array($validationError));

            throw $validationException;
        }

        foreach ($this->behaviours as $behaviour) {
            $behaviour->preDelete($this, $entry);
        }

        if ($this->meta->isLocalized()) {
            $this->deleteLocalized($entry);
        }

        $this->deleteDataInUnlinkedModels($entry);

        $condition = new SimpleCondition(new FieldExpression(ModelTable::PRIMARY_KEY), new SqlExpression($id), Condition::OPERATOR_EQUALS);

        $statement = new DeleteStatement();
        $statement->addTable(new TableExpression($this->getName()));
        $statement->addCondition($condition);

        $this->executeStatement($statement);

        $this->clearCache();

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

        foreach ($this->behaviours as $behaviour) {
            $behaviour->postDelete($this, $entry);
        }

        return $entry;
    }

    /**
     * Deletes the localized data of the provided data
     * @param mixed $entry Data object
     * @return null
     */
    private function deleteLocalized($entry) {
        $this->getLocalizedModel()->deleteLocalizedEntry($entry->id);
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
            $keepRecord = count($fields) != 3;
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

        $query = $model->createQuery(0, null, false);
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
                break;
            }
        }

        if (!$fields) {
            return;
        }

        $deleteData = false;
        if (count($meta->getProperties()) == 1) {
            if (count($belongsTo) == 2) {
                $deleteData = true;
            }
        }

        $table = new TableExpression($unlinkedModelName);
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
                $statement->addTable($table);
                $statement->addValue($field, null);
                $statement->addCondition($condition);

                $this->executeStatement($statement);
            }
        }

        $model->clearCache();
    }

}
