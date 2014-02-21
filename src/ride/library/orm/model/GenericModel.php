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
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\exception\ModelException;
use ride\library\orm\model\data\format\DataFormatter;
use ride\library\orm\model\data\DataFactory;
use ride\library\orm\model\data\DataValidator;
use ride\library\orm\model\meta\ModelMeta;
use ride\library\orm\query\parser\ResultParser;
use ride\library\orm\query\ModelQuery;
use ride\library\orm\OrmManager;
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
     * Depth of the data list
     * @var integer
     */
    protected $dataListDepth;

    /**
     * Initializes the save stack
     * @return null
     */
    protected function initialize() {
        $this->saveStack = array();
        $this->dataListDepth = 1;
    }

    /**
     * Serializes this model
     * @return string Serialized model
     */
    public function serialize() {
        $serialize = array(
            'parent' => parent::serialize(),
            'depth' => $this->dataListDepth,
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

        $this->dataListDepth = $unserialized['depth'];
        $this->saveStack = array();
    }

    /**
     * Gets a list of the data in this model, useful for eg. select fields
     * @param string $locale Code of the locale to fetch the data in (null for the current locale)
     * @return array Array with the id of the data as key and the data formatted with the title format as value
     */
    public function getDataList($locale = null) {
        $query = $this->getDataListQuery();

        $result = $query->query();

        return $this->getDataListResult($result);
    }

    /**
     * Gets the query for the data list
     * @param string $locale Code of the current locale
     * @return ride\library\orm\query\ModelQuery
     */
    public function getDataListQuery($locale = null) {
        $query = $this->createQuery($locale);
        $query->setRecursiveDepth($this->dataListDepth);
        $query->setFetchUnlocalizedData(true);

        return $query;
    }

    /**
     * Parse the query result for a data list
     * @param array $result Query result
     * @return array Array with the id of the data as key and a string
     * representation as value
     */
    public function getDataListResult(array $result) {
        $dataFormatter = $this->orm->getDataFormatter();
        $titleFormat = $this->meta->getDataFormat(DataFormatter::FORMAT_TITLE);

        $list = array();
        foreach ($result as $data) {
            $list[$data->id] = $dataFormatter->formatData($data, $titleFormat);
        }

        return $list;
    }

    /**
     * Gets a data record by it's id
     * @param integer $id Id of the data
     * @param integer $recursiveDepth Recursive depth of the query
     * @param string $locale Locale code
     * @param boolean $fetchUnlocalizedData Flag to see if unlocalized data should be fetched
     * @return mixed Instance of the data if found, null otherwise
     */
    public function getById($id, $recursiveDepth = 1, $locale = null, $fetchUnlocalizedData = false) {
        $query = $this->createQuery($locale);
        $query->setRecursiveDepth($recursiveDepth);
        $query->setIncludeUnlocalizedData(true);
        $query->setFetchUnlocalizedData($fetchUnlocalizedData);

        $query->addCondition('{id} = %1%', $id);

        return $query->queryFirst();
    }

    /**
     * Saves a field from data to the model
     * @param mixed $data A data object or the value to save when the id argument is provided
     * @param string $fieldName Name of the field to save
     * @param integer $id Primary key of the data to save, $data will be considered as the value
     * @param string $locale The locale of the data, only used when the id argument is provided
     * @return null
     * @throws Exception when the field could not be saved
     */
    protected function saveField($data, $fieldName, $id = null, $locale = null) {
        if ($id === null) {
            $this->meta->isValidData($data);

            if (empty($data->id)) {
                throw new ModelException('No primary key found in the data');
            }

            $id = $data->id;
            $value = $data->$fieldName;
        } else {
            $value = $data;
        }

        foreach ($this->behaviours as $behaviour) {
            $behaviour->preUpdateField($this, $id, $fieldName, $value, $locale);
        }

        $this->validateField($fieldName, $value);

        $field = $this->meta->getField($fieldName);
        if ($field->isLocalized()) {
            $this->saveLocalizedField($value, $fieldName, $id, $locale);
        } elseif ($field instanceof HasManyField) {
            $this->saveHasMany($value, $fieldName, $id, false, $field->isDependant());
        } elseif ($field instanceof HasOneField) {
            $this->saveHasOne($value, $fieldName, $id);
        } else {
            if ($field instanceof BelongsToField) {
                $value = $this->saveBelongsTo($value, $fieldName);
            }

            $condition = new SimpleCondition(new FieldExpression(ModelTable::PRIMARY_KEY), new SqlExpression($id), Condition::OPERATOR_EQUALS);

            $statement = new UpdateStatement();
            $statement->addTable(new TableExpression($this->getName()));
            $statement->addValue(new FieldExpression($fieldName), new ScalarExpression($value));
            $statement->addCondition($condition);

            $this->executeStatement($statement);

            $this->clearCache();
        }

        foreach ($this->behaviours as $behaviour) {
            $behaviour->postUpdateField($this, $id, $fieldName, $value, $locale);
        }
    }

    /**
     * Saves a localized field to the localized model
     * @param mixed $data A data object or the value to save when the id argument is provided
     * @param string $fieldName Name of the field to save
     * @param integer $id Primary key of the data to save, $data will be considered as the value
     * @param string $locale The locale of the data, only used when the id argument is provided
     * @return null
     * @throws Exception when the field could not be saved
     */
    private function saveLocalizedField($data, $fieldName, $id, $locale) {
        $locale = $this->getLocale($locale);

        $localizedModel = $this->meta->getLocalizedModel();

        $localizedId = $localizedModel->getLocalizedId($id, $locale);

        $localizedModel->save($data, $fieldName, $localizedId, $locale);
    }

    /**
     * Saves a data object to the model
     * @param mixed $data A data object of this model
     * @return null
     * @throws Exception when the data could not be saved
     */
    protected function saveData($data) {
        if (is_null($data)) {
            return $data;
        }

        $this->meta->isValidData($data);
        $this->validate($data);

        $table = new TableExpression($this->meta->getName());
        $idField = new FieldExpression(ModelTable::PRIMARY_KEY, $table);

        $id = $this->reflectionHelper->getProperty($data, ModelTable::PRIMARY_KEY);

        if (!$id) {
            $statement = new InsertStatement();
            $isNew = true;
        } else {
            if (isset($this->saveStack[$id])) {
                return;
            } else {
                $this->saveStack[$id] = $id;
            }

            $condition = new SimpleCondition($idField, new ScalarExpression($id), Condition::OPERATOR_EQUALS);

            $statement = new UpdateStatement();
            $statement->addCondition($condition);

            $isNew = false;
        }

        foreach ($this->behaviours as $behaviour) {
            if ($isNew) {
                $behaviour->preInsert($this, $data);
            } else {
                $behaviour->preUpdate($this, $data);
            }
        }

        $statement->addTable($table);

        $properties = $this->meta->getProperties();
        foreach ($properties as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY || $field->isLocalized()) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($data, $fieldName);

            if ($field->getType() == 'serialize') {
                $value = serialize($value);
            }

            $statement->addValue(new FieldExpression($fieldName), new ScalarExpression($value));
        }

        $belongsTo = $this->meta->getBelongsTo();
        foreach ($belongsTo as $fieldName => $field) {
            if ($field->isLocalized()) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($data, $fieldName);

            $foreignKey = $this->saveBelongsTo($value, $fieldName);

            $statement->addValue(new FieldExpression($fieldName), new ScalarExpression($foreignKey));
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

                $this->reflectionHelper->setProperty($data, ModelTable::PRIMARY_KEY, $id);
            }

            $this->clearCache();
        }

//        foreach ($belongsTo as $fieldName => $field) {
//            if (!isset($data->$fieldName)) {
//                continue;
//            }
//
//            $data->$fieldName = $this->saveLinkedBelongsTo($data->$fieldName, $fieldName, $field, $data->id);
//        }

        $hasOne = $this->meta->getHasOne();
        foreach ($hasOne as $fieldName => $field) {
            if (!isset($data->$fieldName) || $field->isLocalized()) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($data, $fieldName);

            $this->saveHasOne($value, $fieldName, $id);
        }

        $hasMany = $this->meta->getHasMany();
        foreach ($hasMany as $fieldName => $field) {
            if ($field->isLocalized()) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($data, $fieldName);

            $this->saveHasMany($value, $fieldName, $id, $isNew, $field->isDependant());
        }

        if ($this->meta->isLocalized()) {
            $this->saveLocalizedData($data);
        }

        foreach ($this->behaviours as $behaviour) {
            if ($isNew) {
                $behaviour->postInsert($this, $data);
            } else {
                $behaviour->postUpdate($this, $data);
            }
        }

        unset($this->saveStack[$id]);
    }

    /**
     * Saves the localized fields of the data object to the model
     * @param mixed $data Data object
     * @return null
     */
    private function saveLocalizedData($data) {
        $dataField = LocalizedModel::FIELD_DATA;
        $localeField = LocalizedModel::FIELD_LOCALE;

        if (!isset($data->$localeField)) {
            $data->$localeField = null;
        }

        $localizedModel = $this->getLocalizedModel();

        $localizedData = $localizedModel->createData();
        $this->reflectionHelper->setProperty($localizedData, $localeField, $this->getLocale($this->reflectionHelper->getProperty($data, $localeField)));
        $this->reflectionHelper->setProperty($localizedData, $dataField, $this->reflectionHelper->getProperty($data, ModelTable::PRIMARY_KEY));

        $hasSetFields = false;
        $fields = $this->meta->getLocalizedFields();
        foreach ($fields as $fieldName => $field) {
            $fieldValue = $this->reflectionHelper->getProperty($data, $fieldName);
            if ($fieldValue === null) {
                unset($fields[$fieldName]);

                continue;
            }

            $hasSetFields = true;

            $this->reflectionHelper->setProperty($localizedData, $fieldName, $fieldValue);
        }

        if ($hasSetFields) {
            $localizedModel->save($localizedData);

            foreach ($fields as $fieldName => $field) {
                $this->reflectionHelper->setProperty($data, $fieldName, $this->reflectionHelper->getProperty($localizedData, $fieldName));
            }
        }
    }

    /**
     * Saves a belongs to value to the model of the field
     * @param mixed $data Value of the belongs to field
     * @param string $fieldName Name of the belongs to field
     * @return integer The foreign key of the belongs to value
     */
    private function saveBelongsTo($data, $fieldName) {
        if (empty($data)) {
            return null;
        }

        if (is_numeric($data)) {
            if ($data == 0) {
                return null;
            }

            return $data;
        }

        $model = $this->getRelationModel($fieldName);
        $model->save($data);

        return $this->reflectionHelper->getProperty($data, ModelTable::PRIMARY_KEY);
    }

//    private function saveLinkedBelongsTo($data, $fieldName, $field, $id) {
//        try {
//            $linkModel = $this->meta->getRelationLinkModel($fieldName);
//        } catch (ModelException $e) {
//            return $data;
//        }
//
//        return $data;
//    }

    /**
     * Saves a has one value to the model of the field
     * @param mixed $data Value of the has one field
     * @param string $fieldName Name of the has one field
     * @param integer $id Primary key of the data which is being saved
     * @return null
     */
    private function saveHasOne($data, $fieldName, $id) {
        if (is_null($data)) {
            return;
        }

        $model = $this->meta->getRelationModel($fieldName);
        $foreignKey = $this->meta->getRelationForeignKey($fieldName);

        $data->$foreignKey = $id;

        $model->save($data);
    }

    /**
     * Saves a has many value to the model of the field
     * @param mixed $data Value of the has many field
     * @param string $fieldName Name of the has many field
     * @param integer $id Primary key of the data which is being saved
     * @param boolean $isNew Flag to see if this is an insert or an update
     * @param boolean $isDependant Flag to see if the values of the field are dependant on this model
     * @return null
     */
    private function saveHasMany($data, $fieldName, $id, $isNew, $isDependant) {
        if (is_null($data)) {
            return;
        }

        if ($this->meta->isHasManyAndBelongsToMany($fieldName)) {
            $this->saveHasManyAndBelongsToMany($data, $fieldName, $id, $isNew);
        } else {
            $this->saveHasManyAndBelongsTo($data, $fieldName, $id, $isNew, $isDependant);
        }
    }

    /**
     * Saves a has many value to the model of the field. This is a many to many field.
     * @param mixed $data Value of the has many field
     * @param string $fieldName Name of the has many field
     * @param integer $id Primary key of the data which is being saved
     * @param boolean $isNew Flag to see whether this is an insert or an update
     * @return null
     */
    private function saveHasManyAndBelongsToMany($data, $fieldName, $id, $isNew) {
        if (!is_array($data)) {
            throw new ModelException('Provided value for ' . $fieldName . ' should be an array');
        }

        $foreignKeys = $this->meta->getRelationForeignKey($fieldName);

        $foreignKeyToSelf = null;
        if (!is_array($foreignKeys)) {
            $foreignKeyToSelf = $this->meta->getRelationForeignKeyToSelf($fieldName);
            $foreignKeys = array($foreignKeys, $foreignKeyToSelf);
        } else {
            $foreignKeys = array_values($foreignKeys);
        }

        if (!$isNew) {
            if ($foreignKeyToSelf) {
                // relation with other model
                $this->deleteOldHasManyAndBelongsToMany($id, $fieldName, $foreignKeyToSelf);
            } else {
                // relation with self
                foreach ($foreignKeys as $foreignKey) {
                    $this->deleteOldHasManyAndBelongsToMany($id, $fieldName, $foreignKey);
                }
            }
        }

        $model = $this->getRelationModel($fieldName);
        $linkModel = $this->getRelationLinkModel($fieldName);
        $linkTable = new TableExpression($linkModel->getName());
        $foreignKey1 = new FieldExpression($foreignKeys[0]);
        $foreignKey2 = new FieldExpression($foreignKeys[1]);

        foreach ($data as $recordId => $record) {
            if (!is_numeric($record)) {
                $model->save($record);

                $recordNewId = $this->reflectionHelper->getProperty($record, ModelTable::PRIMARY_KEY);
            } else {
                $recordNewId = $record;
            }

            $statement = new InsertStatement();
            $statement->addTable($linkTable);
            $statement->addValue($foreignKey1, $recordNewId);
            $statement->addValue($foreignKey2, $id);

            $this->executeStatement($statement);

            $linkModel->clearCache();
        }
    }

    /**
     * Deletes the links for the provided many to many field
     * @param integer $id Primary key of the data which is being saved
     * @param string $fieldName Name of the has many field
     * @param ride\library\orm\definition\field\ModelField $foreignKey Definition of the foreign key field in the link model
     * @return null
     */
    private function deleteOldHasManyAndBelongsToMany($id, $fieldName, $foreignKey) {
        $linkModel = $this->getRelationLinkModel($fieldName);

        $condition = new SimpleCondition(new FieldExpression($foreignKey), new ScalarExpression($id), Condition::OPERATOR_EQUALS);

        $statement = new DeleteStatement();
        $statement->addTable(new TableExpression($linkModel->getName()));
        $statement->addCondition($condition);

        $this->executeStatement($statement);

        $linkModel->clearCache();
    }

    /**
     * Saves a has many value to the model of the field. This is a many to one field.
     * @param mixed $data Value of the has many field
     * @param string $fieldName Name of the has many field
     * @param integer $id Primary key of the data which is being saved
     * @param boolean $isNew Flag to see whether this is an insert or an update
     * @param boolean $isDependant Flag to see if the values of the field are dependant on this model
     * @return null
     */
    private function saveHasManyAndBelongsTo($data, $fieldName, $id, $isNew, $isDependant) {
        $model = $this->getRelationModel($fieldName);

        $foreignKey = $this->meta->getRelationForeignKey($fieldName);

        if (!$isNew) {
            $oldHasMany = $this->findOldHasManyAndBelongsTo($model, $foreignKey, $id);
        }

        foreach ($data as $record) {
            if (is_numeric($record)) {
                if (!$isNew && array_key_exists($record, $oldHasMany)) {
                    unset($oldHasMany[$record]);
                }

                continue;
            }

            $this->reflectionHelper->setProperty($record, $foreignKey, $id);

            $model->save($record);

            $recordId = $this->reflectionHelper->getProperty($record, ModelTable::PRIMARY_KEY);
            if (!$isNew && array_key_exists($recordId, $oldHasMany)) {
                unset($oldHasMany[$recordId]);
            }
        }

        if (!$isNew && $oldHasMany) {
            $this->deleteOldHasManyAndBelongsTo($model, $foreignKey, $oldHasMany, $isDependant);
        }
    }

    /**
     * Gets the primary keys of the has many values
     * @param Model $model Model of the has many field
     * @param string $foreignKey Name of the foreign key to this model
     * @param integer $id Value for the foreign key
     * @return array Array with the primary key of the has many value as key and value
     */
    private function findOldHasManyAndBelongsTo($model, $foreignKey, $id) {
        $condition = new SimpleCondition(new FieldExpression($foreignKey), new ScalarExpression($id), Condition::OPERATOR_EQUALS);

        $statement = new SelectStatement();
        $statement->addTable(new TableExpression($model->getName()));
        $statement->addField(new FieldExpression(ModelTable::PRIMARY_KEY));
        $statement->addCondition($condition);

        $result = $this->executeStatement($statement);

        $model->clearCache();

        $oldHasMany = array();
        foreach ($result as $record) {
            $oldHasMany[$record[ModelTable::PRIMARY_KEY]] = $record[ModelTable::PRIMARY_KEY];
        }

        return $oldHasMany;
    }

    /**
     * Deletes the old has many values which are not saved
     * @param Model $model Model of the has many field
     * @param string $foreignKey Name of the foreign key to this model
     * @param array $oldHasMany Array with the primary key of the has many value as key and value
     * @param boolean $idDependant Flag to see whether the has many value is dependant on this model
     * @return null
     */
    private function deleteOldHasManyAndBelongsTo($model, $foreignKey, $oldHasMany, $isDependant) {
        if ($isDependant) {
            $model->delete($oldHasMany);

            return;
        }

        foreach ($oldHasMany as $id) {
            $model->saveField(null, $foreignKey, $id);
        }
    }

    /**
     * Deletes data from this model
     * @param mixed $data Primary key of the data or a data object of this model
     * @return null
     */
    protected function deleteData($data) {
        $id = $this->getPrimaryKey($data);

        $query = $this->createQuery();
        $query->setIncludeUnlocalizedData(true);
        $query->addCondition('{id} = %1%', $id);

        $data = $query->queryFirst();

        if ($data == null) {
            return;
        }

        if ($this->meta->willBlockDeleteWhenUsed() && $this->isDataReferencedInUnlinkedModels($data)) {
            $validationError = new ValidationError('orm.error.data.used', '%data% is still in use by another record', array('data' => $this->meta->formatData($data)));

            $validationException = new ValidationException();
            $validationException->addErrors('id', array($validationError));

            throw $validationException;
        }

        foreach ($this->behaviours as $behaviour) {
            $behaviour->preDelete($this, $data);
        }

        if ($this->meta->isLocalized()) {
            $this->deleteLocalized($data);
        }

        $this->deleteDataInUnlinkedModels($data);

        $condition = new SimpleCondition(new FieldExpression(ModelTable::PRIMARY_KEY), new SqlExpression($id), Condition::OPERATOR_EQUALS);

        $statement = new DeleteStatement();
        $statement->addTable(new TableExpression($this->getName()));
        $statement->addCondition($condition);

        $this->executeStatement($statement);

        $this->clearCache();

        $belongsTo = $this->meta->getBelongsTo();
        foreach ($belongsTo as $fieldName => $field) {
            $this->deleteBelongsTo($fieldName, $field, $data);
        }

        $hasOne = $this->meta->getHasOne();
        foreach ($hasOne as $fieldName => $field) {
            $this->deleteBelongsTo($fieldName, $field, $data);
        }

        $hasMany = $this->meta->getHasMany();
        foreach ($hasMany as $fieldName => $field) {
            $this->deleteHasMany($fieldName, $field, $data);
        }

        foreach ($this->behaviours as $behaviour) {
            $behaviour->postDelete($this, $data);
        }

        return $data;
    }

    /**
     * Deletes the localized data of the provided data
     * @param mixed $data Data object
     * @return null
     */
    private function deleteLocalized($data) {
        $this->getLocalizedModel()->deleteLocalizedData($data->id);
    }

    /**
     * Deletes the value of the provided relation field in the provided data. This will only be done if the
     * field is dependant.
     * @param string $fieldName Name of the relation field
     * @param ride\library\orm\definition\field\RelationField $field Definition of the relation field
     * @param mixed $data Data obiect
     * @return null
     */
    private function deleteBelongsTo($fieldName, RelationField $field, $data) {
        if ($field->isDependant() && $data->$fieldName) {
            $model = $this->getRelationModel($fieldName);
            $model->delete($data->$fieldName);
        }
    }

    /**
     * Deletes or clears the values of the provided has many field in the provided data.
     * @param string $fieldName Name of the has many field
     * @param ride\library\orm\definition\field\HasManyField $field Definition of the has many field
     * @param mixed $data Data obiect
     * @return null
     */
    private function deleteHasMany($fieldName, HasManyField $field, $data) {
        $model = $this->getRelationModel($fieldName);

        if (!$this->meta->isHasManyAndBelongsToMany($fieldName)) {
            if ($field->isDependant()) {
                if ($data->$fieldName) {
                    foreach ($data->$fieldName as $record) {
                        $model->delete($record);
                    }
                }
            } else {
                $this->clearHasMany($this->getRelationModelTable($fieldName), $data->id, true);
            }
            return;
        }

        $linkModelTable = $this->getRelationLinkModelTable($fieldName);

        if ($field->isDependant()) {
            foreach ($data->$fieldName as $record) {
                $model->delete($record);
            }

            $keepRecord = false;
        } else {
            $fields = $linkModelTable->getFields();
            $keepRecord = count($fields) != 3;
        }

        $this->clearHasMany($linkModelTable, $data->id, $keepRecord);
    }

    /**
     * Deletes or clears, depending on the keepRecord argument, the values of the provided table
     * which have a relation with the provided data
     * @param ride\library\orm\definition\ModelTable $modelTable Table definition of the model of the has many relation
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
     * @param mixed $data Data to check for references
     * @return null
     * @throws ride\library\validation\exception\ValidationException when the data is referenced in another model
     */
    protected function isDataReferencedInUnlinkedModels($data) {
        $foundReference = false;

        $unlinkedModels = $this->meta->getUnlinkedModels();
        foreach ($unlinkedModels as $modelName) {
            $foundReference = $this->isDataReferencedInModel($modelName, $data);
            if ($foundReference) {
                break;
            }
        }

        return $foundReference;
    }

    /**
     * Checks whether the provided data is references in the provided model
     * @param string $modelName Name of the model to check for references
     * @param mixed $data Data object to check for
     * @return boolean True if the provided model references the provided data, false otherwise
     */
    private function isDataReferencedInModel($modelName, $data) {
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
            $query->addCondition('{' . $fieldName . '} = %1%', $data->id);
        }

        return $query->count() ? true : false;
    }

    /**
     * Deletes or clears the data in models which use this model but are not linked from this model
     * @param mixed $data Data object
     * @return null
     */
    private function deleteDataInUnlinkedModels($data) {
        $unlinkedModels = $this->meta->getUnlinkedModels();

        foreach ($unlinkedModels as $unlinkedModelName) {
            $this->deleteDataInModel($unlinkedModelName, $data);
        }
    }

    /**
     * Deletes or clears the data in the provided model which has links with the provided data
     * @param string $unlinkedModelName Name of the model which has links with this model but which are not linked from this model
     * @param mixed $data Data object
     * @return null
     */
    private function deleteDataInModel($unlinkedModelName, $data) {
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
        $id = new SqlExpression($data->id);

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