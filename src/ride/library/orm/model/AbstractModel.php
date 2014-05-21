<?php

namespace ride\library\orm\model;

use ride\library\database\manipulation\statement\Statement;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\HasOneField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\exception\ModelException;
use ride\library\orm\model\behaviour\Behaviour;
use ride\library\orm\model\data\validator\GenericDataValidator;
use ride\library\orm\model\meta\ModelMeta;
use ride\library\orm\query\parser\ResultParser;
use ride\library\orm\query\CachedModelQuery;
use ride\library\orm\OrmManager;
use ride\library\reflection\ReflectionHelper;
use ride\library\validation\constraint\Constraint;
use ride\library\validation\exception\ValidationException;

use \Exception;
use \Serializable;

/**
 * Abstract implementation of a data model
 */
abstract class AbstractModel implements Model, Serializable {

    /**
     * Instance of the model manager
     * @var \ride\library\orm\OrmManager
     */
    protected $orm;

    /**
     * Factory for data objects
     * @var \ride\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Meta of this model
     * @var ModelMeta
     */
    protected $meta;

    /**
     * Behaviours of the model
     * @var array
     */
    protected $behaviours;

    /**
     * Validation constraint
     * @var \ride\library\validation\constraint\Constraint
     */
    protected $validationConstraint;

    /**
     * Parser for database results
     * @var \ride\library\orm\query\parser\ResultParser
     */
    protected $resultParser;

    /**
     * Constructs a new data model
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param ModelMeta $modelMeta Meta data of the model
     * @param array $behaviours
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, ModelMeta $meta, array $behaviours = array()) {
        $this->reflectionHelper = $reflectionHelper;
        $this->meta = $meta;
        $this->resultParser = new ResultParser($this);
        $this->behaviours = array();

        foreach ($behaviours as $behaviour) {
            $this->addBehaviour($behaviour);
        }

        $this->initialize();
    }

    /**
     * Gets the instance of the reflection helper
     * @return \ride\library\reflection\ReflectionHelper
     */
    public function getReflectionHelper() {
        return $this->reflectionHelper;
    }

    /**
     * Hook performed at the end of the constructor
     * @return null
     */
    protected function initialize() {

    }

    /**
     * Adds a behaviour to the model
     * @param \ride\library\orm\model\behaviour\Behaviour $behaviour
     * @return null
     */
    protected function addBehaviour(Behaviour $behaviour) {
        $this->behaviours[] = $behaviour;
    }

    public function getBehaviours() {
        return $this->behaviours;
    }

    /**
     * Serializes this model
     * @return string Serialized model
     */
    public function serialize() {
        $serialize = array(
            'reflectionHelper' => $this->reflectionHelper,
            'meta' => $this->meta,
        );

        if ($this->behaviours) {
            $serialize['behaviours'] = $this->behaviours;
        }

        return serialize($serialize);
    }

    /**
     * Unserializes the provided string into a model
     * @param string $serialized Serialized string of a model
     * @return null
     */
    public function unserialize($serialized) {
        $unserialized = unserialize($serialized);

        $this->reflectionHelper = $unserialized['reflectionHelper'];
        $this->meta = $unserialized['meta'];

        if (isset($unserialized['behaviours'])) {
            $this->behaviours = $unserialized['behaviours'];
        } else {
            $this->behaviours = array();
        }

        $this->resultParser = new ResultParser($this);
    }

    /**
     * Sets the model manager to this model
     * @param \\ride\library\orm\OrmManager $orm
     * @return null
     */
    public function setOrmManager(OrmManager $orm) {
        $this->orm = $orm;
    }

    /**
     * Gets the model manager from this model
     * @return \ride\library\orm\OrmManager
     */
    public function getOrmManager() {
        return $this->orm;
    }

    /**
     * Gets the name of this model
     * @return string
     */
    public function getName() {
        return $this->meta->getName();
    }

    /**
     * Gets the meta data of this model
     * @return ModelMeta
     */
    public function getMeta() {
        return $this->meta;
    }

    /**
     * Gets the database result parser of this model
     * @return \ride\library\orm\query\parser\ResultParser
     */
    public function getResultParser() {
        return $this->resultParser;
    }

    /**
     * Creates a new data object for this model
     * @param boolean $initialize True to create a data object with default values (default), false to create an empty data object
     * @return mixed A new data object for this model
     */
    public function createData(array $properties = array()) {
        $fields = $this->meta->getFields();
        foreach ($fields as $field) {
            $name = $field->getName();

            if (isset($properties[$name])) {
                continue;
            }

            if ($field instanceof HasManyField) {
                $properties[$name] = array();

                continue;
            }

            $properties[$name] = $field->getDefaultValue();
        }

        $data = $this->reflectionHelper->createData($this->meta->getDataClassName(), $properties);

        foreach ($this->behaviours as $behaviour) {
            $behaviour->postCreateData($this, $data);
        }

        $data->_state = array();

        return $data;
    }

    /**
     * Creates a model query for this model
     * @param string $locale Locale code of the data
     * @return \ride\library\orm\query\ModelQuery
     */
    public function createQuery($locale = null) {
        return $this->orm->createQuery($this, $locale);
    }

    /**
     * Converts a data instance to an array
     * @param mixed $data Primary key or a data instance
     * @return array|integer|null
     */
    public function convertDataToArray($data) {
        if ($data === null || is_numeric($data)) {
            return $data;
        }

        $this->meta->isValidData($data);

        $array = array();

        $fields = $this->meta->getFields();
        foreach ($fields as $field) {
            $name = $field->getName();
            $value = $this->reflectionHelper->getProperty($data, $name);

            if (!$value || $field instanceof PropertyField) {
                $array[$name] = $value;
            } elseif ($field instanceof HasManyField) {
                $relationModel = $this->getModel($field->getRelationModelName());

                foreach ($value as $index => $hasValue) {
                    $array[$name][$index] = $relationModel->convertDataToArray($hasValue);
                }
            } else {
                $relationModel = $this->getModel($field->getRelationModelName());

                $array[$name] = $relationModel->convertDataToArray($value);
            }
        }

        if ($this->meta->isLocalized()) {
            $locale = $this->reflectionHelper->getProperty($data, LocalizedModel::FIELD_LOCALE);

            if ($locale && !isset($array['locale'])) {
                $array['locale'] = $locale;
            }
        }

        return $array;
    }

    /**
     * Validates a data object of this model
     * @param mixed $data Data object of the model
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the validation factory is not set
     * @throws \ride\library\validation\exception\ValidationException when one of the fields is not valid
     */
    public function validate($data) {
        $exception = new ValidationException('Validation errors occured in ' . $this->getName());

        foreach ($this->behaviours as $behaviour) {
            $behaviour->preValidate($this, $data, $exception);
        }

        $constraint = $this->getValidationConstraint();
        if ($constraint) {
            $constraint->validateData($data, $exception);
        }

        foreach ($this->behaviours as $behaviour) {
            $behaviour->postValidate($this, $data, $exception);
        }

        if ($exception->hasErrors()) {
            throw $exception;
        }
    }

    /**
     * Validates a value for a certain field of this model
     * @param string $fieldName Name of the field
     * @param mixed $value Value to validate
     * @return null
     * @throws \ride\library\validation\exception\ValidationException when the field is not valid
     */
    protected function validateField($fieldName, $value) {
        $exception = new ValidationException('Validation errors occured in ' . $this->getName());

        $constraint = $this->getValidationConstraint();
        $constraint->validateProperty($fieldName, $value, $exception);

        if ($exception->hasErrors()) {
            throw $exception;
        }
    }

    /**
     * Gets the data validator
     * @return \ride\library\validation\constraint\Constraint
     */
    public function getValidationConstraint() {
        if ($this->validationConstraint) {
            return $this->validationConstraint;
        }

        $this->validationConstraint = $this->meta->getValidationConstraint($this->getOrmManager()->getValidationFactory());
        if ($this->validationConstraint) {
            $this->initializeValidationConstraint($this->validationConstraint);
        }

        return $this->validationConstraint;
    }

    /**
     * Hook to process the validation constraint before it's assigned to this
     * model
     * @param \ride\library\validation\constraint\Constraint $constraint
     * @return null
     */
    protected function initializeValidationConstraint(Constraint $constraint) {

    }

    /**
     * Saves data to the model
     * @param mixed $data A data object or an array of data objects when no id argument is provided, the value for the field otherwise
     * @param string $fieldName Name of the field to save
     * @param integer $id Primary key of the data to save, $data will be considered as the value for the provided field name
     * @param string $locale The locale of the data, only used when the id argument is provided
     * @return null
     * @throws Exception when the data could not be saved
     */
    public function save($data, $fieldName = null, $id = null, $locale = null) {
        $isTransactionStarted = $this->beginTransaction();
        $isFieldNameProvided = !is_null($fieldName);

        try {
            if (is_array($data)) {
                if ($isFieldNameProvided) {
                    foreach ($data as $d) {
                        $this->saveField($d, $fieldName);
                    }
                } else {
                    foreach ($data as $d) {
                        $this->saveData($d);
                    }
                }
            } elseif ($isFieldNameProvided) {
                if (is_array($id)) {
                    foreach ($id as $pk) {
                        if (is_object($pk)) {
                            $this->saveField($data, $fieldName, $pk->id, $locale);
                        } else {
                            $this->saveField($data, $fieldName, $pk, $locale);
                        }
                    }
                } else {
                    $this->saveField($data, $fieldName, $id, $locale);
                }
            } else {
                $this->saveData($data);
            }

            $this->commitTransaction($isTransactionStarted);
        } catch (Exception $e) {
            $this->rollbackTransaction($isTransactionStarted);
            throw $e;
        }
    }

    /**
     * Saves a data object to the model
     * @param mixed $data A data object of this model
     * @return null
     * @throws Exception when the data could not be saved
     */
    abstract protected function saveData($data);

    /**
     * Saves a field from data to the model
     * @param mixed $data A data object or the value to save when the id argument is provided
     * @param string $fieldName Name of the field to save
     * @param integer $id Primary key of the data to save, $data will be considered as the value
     * @param string $locale The locale of the data, only used when the id argument is provided
     * @return null
     * @throws Exception when the field could not be saved
     */
    abstract protected function saveField($data, $fieldName, $id = null, $locale = null);

    /**
     * Deletes data from this model
     * @param mixed $data Primary key of the data, a data object or an array with the previous as value
     * @return null
     * @throws Exception when the data could not be deleted
     */
    public function delete($data) {
        $isTransactionStarted = $this->beginTransaction();

        try {
            if (is_array($data)) {
                foreach ($data as $index => $d) {
                    $data[$index] = $this->deleteData($d);
                }
            } else {
                $data = $this->deleteData($data);
            }

            $this->commitTransaction($isTransactionStarted);
        } catch (Exception $e) {
            $this->rollbackTransaction($isTransactionStarted);

            throw $e;
        }

        return $data;
    }

    /**
     * Deletes data from this model
     * @param mixed $data Primary key of the data or a data object of this model
     * @return mixed The full data which has been deleted
     */
    abstract protected function deleteData($data);

    /**
     * Clears the result cache of this model
     * @return null
     */
    public function clearCache() {
        $resultCache = $this->orm->getResultCache();
        if ($resultCache) {
            $resultCache->flush($this->getName());
        }
    }

    /**
     * Gets the primary key of data
     * @param mixed $data Primary key or a data object
     * @return integer The primary key of the data
     * @throws \ride\library\orm\exception\ModelException when no primary key could be retrieved from the data
     */
    protected function getPrimaryKey($data) {
        if (is_numeric($data)) {
            return $data;
        }

        $this->meta->isValidData($data);

        if (!empty($data->id)) {
            return $data->id;
        }

        throw new ModelException('No primary key found in the provided data');
    }

    /**
     * Gets the locale for the data
     * @param string $locale when no locale passed, the current locale will be used
     * @return string Code of the locale
     * @throws \ride\library\orm\exception\OrmException when the provided locale is invalid
     */
    protected function getLocale($locale) {
    	if ($locale === null) {
        	$locale = $this->orm->getLocale();
        } else if (!is_string($locale) || $locale == '') {
            throw new OrmException('Provided locale is invalid');
        }

        return $locale;
    }

    /**
     * Gets the model table definition of the relation model of the provided field
     * @param string $fieldName Name of the relation field
     * @return \ride\library\orm\model\LocalizedModel
     */
    public function getLocalizedModel() {
        $localizedModelName = $this->meta->getLocalizedModelName();

        return $this->getModel($localizedModelName);
    }

    /**
     * Gets the link model of the provided relation field
     * @param string $fieldName Name of the relation field
     * @return \ride\library\orm\model\Model
     */
    protected function getRelationLinkModel($fieldName) {
        $relationLinkModelName = $this->meta->getRelationLinkModelName($fieldName);

        if (!$relationLinkModelName) {
            return null;
        }

        return $this->getModel($relationLinkModelName);
    }

    /**
     * Gets the table definition of the relation link model of the provided field
     * @param string $fieldName Name of the relation field
     * @return \ride\library\orm\definition\ModelTable
     */
    protected function getRelationLinkModelTable($fieldName) {
        $linkModelName = $this->meta->getRelationLinkModelName($fieldName);
        $linkModel = $this->getModel($linkModelName);

        return $linkModel->getMeta()->getModelTable();
    }

    /**
     * Gets the relation model of the provided field
     * @param string $fieldName Name of the relation field
     * @return \ride\library\orm\model\Model
     */
    public function getRelationModel($fieldName) {
        $relationModelName = $this->meta->getRelationModelName($fieldName);

        return $this->getModel($relationModelName);
    }

    /**
     * Gets the model table definition of the relation model of the provided field
     * @param string $fieldName Name of the relation field
     * @return \ride\library\orm\definition\ModelTable
     */
    protected function getRelationModelTable($fieldName) {
        $relationModelName = $this->meta->getRelationModelName($fieldName);
        $relationModel = $this->getModel($relationModelName);

        return $relationModel->getMeta()->getModelTable();
    }

    /**
     * Gets another model
     * @param string $modelName Name of the model
     * @return Model
     * @throws \ride\library\orm\exception\OrmException when the provided model could not be retrieved
     */
    protected function getModel($modelName) {
        return $this->orm->getModel($modelName);
    }

    /**
     * Executes a statement on the database connection of this model
     * @param ride\library\database\manipulation\statement\Statement
     * @return \ride\library\database\DatabaseResult
     */
    protected function executeStatement(Statement $statement) {
        $connection = $this->orm->getConnection();

        return $connection->executeStatement($statement);
    }

    /**
     * Starts a new transaction on the database connection of this model
     * @return boolean True if a new transaction is started, false when a transaction is already in progress
     */
    protected function beginTransaction() {
        $connection = $this->orm->getConnection();

        return $connection->beginTransaction();
    }

    /**
     * Performs a commit on the transaction on the database connection of this model
     * @param boolean $isTransactionStarted The commit is only performed when true is provided
     * @return null
     */
    protected function commitTransaction($isTransactionStarted) {
        if ($isTransactionStarted) {
            $connection = $this->orm->getConnection();
            $connection->commitTransaction();
        }
    }

    /**
     * Performs a rollback on the transaction on the database connection of this model
     * @param boolean $isTransactionStarted The rollback is only performed when true is provided
     * @return null
     */
    protected function rollbackTransaction($isTransactionStarted) {
        if (!$isTransactionStarted) {
            return;
        }

        $connection = $this->orm->getConnection();
        $connection->rollbackTransaction();
    }

}
