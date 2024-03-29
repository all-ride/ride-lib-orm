<?php

namespace ride\library\orm\model;

use ride\library\database\manipulation\statement\Statement;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\HasOneField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\exception\ModelException;
use ride\library\orm\exception\OrmException;
use ride\library\orm\entry\format\EntryFormatter;
use ride\library\orm\entry\Entry;
use ride\library\orm\meta\ModelMeta;
use ride\library\orm\model\behaviour\Behaviour;
use ride\library\orm\model\data\validator\GenericDataValidator;
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
abstract class AbstractModel implements Model {

    /**
     * Factory for data objects
     * @var \ride\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Instance of the model manager
     * @var \ride\library\orm\OrmManager
     */
    protected $orm;

    /**
     * Meta of this model
     * @var \ride\library\orm\meta\ModelMeta
     */
    protected $meta;

    /**
     * Parser for database results
     * @var \ride\library\orm\query\parser\ResultParser
     */
    protected $resultParser;

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
     * Stack with the primary keys of the data which is saved, to skip save loops
     * @var array
     */
    protected $saveStack;

    /**
     * Constructs a new data model
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param \ride\library\orm\meta\ModelMeta $modelMeta Meta data of the model
     * @param array $behaviours
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, ModelMeta $meta, array $behaviours = []) {
        $this->reflectionHelper = $reflectionHelper;
        $this->meta = $meta;
        $this->proxies = [];
        $this->behaviours = [];

        foreach ($behaviours as $behaviour) {
            $this->addBehaviour($behaviour);
        }

        $this->initialize();
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

    /**
     * Gets the behaviours of this model
     * @return array
     */
    public function getBehaviours() {
        return $this->behaviours;
    }

    /**
     * Serializes this model
     * @return string Serialized model
     */
    public function __serialize() {
        $serialize = [
            'reflectionHelper' => $this->reflectionHelper,
            'meta' => $this->meta,
        ];

        if ($this->behaviours) {
            $serialize['behaviours'] = $this->behaviours;
        }

        return $serialize;
    }

    /**
     * Unserializes the provided string into a model
     * @param array $data Serialized string of a model
     * @return null
     */
    public function __unserialize(array $data) {
        $this->reflectionHelper = $data['reflectionHelper'];
        $this->meta = $data['meta'];

        if (isset($data['behaviours'])) {
            $this->behaviours = $data['behaviours'];
        } else {
            $this->behaviours = [];
        }

        $this->proxies = [];
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
     * Gets the instance of the reflection helper
     * @return \ride\library\reflection\ReflectionHelper
     */
    public function getReflectionHelper() {
        return $this->reflectionHelper;
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
     * @return \ride\library\orm\meta\ModelMeta
     */
    public function getMeta() {
        return $this->meta;
    }

    /**
     * Gets the database result parser of this model
     * @return \ride\library\orm\query\parser\ResultParser
     */
    public function getResultParser() {
        if (!isset($this->resultParser)) {
            $this->resultParser = new ResultParser($this);
        }

        return $this->resultParser;
    }

    /**
     * Creates a new entry for this model
     * @param array $properties Propery values to initialize the entry with
     * @return mixed New entry for this model
     */
    public function createEntry(array $properties = []) {
        $fields = $this->meta->getFields();
        foreach ($fields as $field) {
            $name = $field->getName();

            if (isset($properties[$name])) {
                continue;
            }

            if ($field instanceof HasManyField) {
                $properties[$name] = [];

                continue;
            }

            $properties[$name] = $field->getDefaultValue();

            if ($field instanceof BelongsToField && isset($properties[$name]) && !is_object($properties[$name])) {
                if ($properties[$name] == '0') {
                    $properties[$name] = $this->getRelationModel($name)->createEntry();
                } else {
                    $properties[$name] = $this->getRelationModel($name)->createProxy($properties[$name]);
                }
            }
        }

        $entry = $this->reflectionHelper->createData($this->meta->getEntryClassName(), $properties);

        foreach ($this->behaviours as $behaviour) {
            $behaviour->postCreateEntry($this, $entry);
        }

        return $entry;
    }

    /**
     * Creates an entry proxy for this model
     * @param integer|string $id Primary key of the entry
     * @param string|null $locale Code of the locale
     * @param array $properties Known properties of the entry instance
     * @return mixed An entry proxy instance for this model
     */
    public function createProxy($id, $locale = null, array $properties = []) {
        if (!is_scalar($id)) {
            throw new OrmException('Could not create a proxy for ' . $this->getName() . ': no valid id provided (' . gettype($id) . ')');
        } elseif (!$id && isset($properties[ModelTable::PRIMARY_KEY])) {
            $id = $properties[ModelTable::PRIMARY_KEY];
        }

        if ($locale === null && isset($properties['locale']) && $this->meta->isLocalized()) {
            $locale = $properties['locale'];
        }

        $locale = $this->getLocale($locale);

        if (!isset($this->saveStack[$id]) && !$properties && isset($this->proxies[$id][$locale]) && $this->proxies[$id][$locale]->getEntryState() === Entry::STATE_CLEAN) {
            return $this->proxies[$id][$locale];
        }

        if ($this->meta->isLocalized()) {
            $properties['locale'] = $locale;
        }

        $properties = [
            'model' => $this,
            'id' => $id,
            'properties' => $properties,
        ];

        $proxy = $this->reflectionHelper->createData($this->meta->getProxyClassName(), $properties);

        if ($id) {
            $this->proxies[$id][$locale] = $proxy;
        }

        return $proxy;
    }

    /**
     * Clears the proxy cache of this model
     * @return null
     */
    public function clearProxies() {
        $this->proxies = [];
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
     * Gets an entry list as a flat list
     * @param string $locale Code of the locale
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @return array Array with the id of the entry as key and the title format
     * as value
     */
    public function getEntryList($locale = null, $fetchUnlocalized = false) {
        $locale = $this->getLocale($locale);

        if (isset($this->list[$locale])) {
            return $this->list[$locale];
        }

        $page = 1;
        $limit = 2000;
        $this->list[$locale] = [];

        do {
            $entries = $this->find([
                'limit' => $limit,
                'page' => $page,
            ], $locale, $fetchUnlocalized);

            $this->list[$locale] += $this->getOptionsFromEntries($entries);

            $page++;
        } while (count($entries) == $limit);

        return $this->list[$locale];
    }

    /**
     * Parses entries into an array of formatted entries
     * @param array $entries Array of entries from this model
     * @return array Array with the primary key of the entry as key and the
     * entry formatted with title format as value
     */
    public function getOptionsFromEntries(array $entries) {
        $entryFormatter = $this->orm->getEntryFormatter();
        $format = $this->meta->getFormat(EntryFormatter::FORMAT_LIST, false);
        if (!$format) {
            $format = $this->meta->getFormat(EntryFormatter::FORMAT_TITLE);
        }

        $options = [];
        foreach ($entries as $entry) {
            $options[$this->reflectionHelper->getProperty($entry, ModelTable::PRIMARY_KEY)] = $entryFormatter->formatEntry($entry, $format);
        }

        return $options;
    }

    /**
     * Gets an entry based on an id or open structure
     * @param string|array $value Id of the entry, an array of ids, a structure
     * of values or an array of structures of values
     * @param string $locale Code of the locale
     * @return mixed|null Requested entry or null when the entry could not be
     * found
     */
    public function getEntryFromValue($value, $locale) {
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_numeric($value)) {
            if ($value < 1) {
                return null;
            }

            // numeric value, get entry proxy
            return $this->createProxy($value, $locale);
        }

        if (!is_array($value)) {
            // not numeric and not an array, invalid value
            throw new OrmException('Could not get ' . $this->getName() . ' entry for ' . $value . ': invalid value provided');
        }

        if (isset($value[0])) {
            // 0-index value set, assume array of entries
            $result = [];

            foreach ($value as $entry) {
                $result[] = $this->getEntryFromValue($entry, $locale);
            }

            return $result;
        }

        // first value not set, assume value struct
        $entry = null;

        if (isset($value[ModelTable::PRIMARY_KEY])) {
            $entry = $this->getById($value[ModelTable::PRIMARY_KEY], $locale);
        }

        if (!$entry) {
            $entry = $this->createEntry();
        }

        $fields = $this->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if (!isset($value[$fieldName])) {
                continue;
            }

            if (!$field instanceof PropertyField) {
                if (is_array($value[$fieldName]) && !$value[$fieldName]) {
                    // ignore empty arrays
                } else {
                    $relationModel = $this->orm->getModel($field->getRelationModelName());

                    $value[$fieldName] = $relationModel->getEntryFromValue($value[$fieldName], $locale);
                }
            }

            $this->reflectionHelper->setProperty($entry, $fieldName, $value[$fieldName]);
        }

        return $entry;
    }

    /**
     * Converts a data instance to an array
     * @param mixed $data Primary key or a data instance
     * @return array|integer|null
     */
    public function convertEntryToArray($data, array $omitFields = null, $level = 1) {
        if ($data === null || is_numeric($data)) {
            return $data;
        }

        $this->meta->isValidEntry($data);

        $array = [];

        $fields = $this->meta->getFields();
        foreach ($fields as $field) {
            $name = $field->getName();
            if ($omitFields && in_array($name, $omitFields)) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($data, $name);

            if (!$value || $field instanceof PropertyField) {
                $array[$name] = $value;
            } elseif ($field instanceof HasManyField) {
                $relationModel = $this->getModel($field->getRelationModelName());
                $foreignKey = $this->meta->getRelationForeignKey($name);

                foreach ($value as $index => $hasValue) {
                    if ($level == 0) {
                        $array[$name][] = $hasValue->id;
                    } else {
                        $array[$name][] = $relationModel->convertEntryToArray($hasValue, [$foreignKey], $level - 1);
                    }
                }
            } else {
                $relationModel = $this->getModel($field->getRelationModelName());

                if ($level == 0) {
                    $array[$name] = $value->id;
                } else {
                    $array[$name] = $relationModel->convertEntryToArray($value, null, $level - 1);
                }
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
     * Validates an entry of this model
     * @param mixed $entry Entry instance or entry properties of this model
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the validation
     * factory is not set
     * @throws \ride\library\validation\exception\ValidationException when one
     * of the fields is not valid
     */
    public function validate($entry) {
        $exception = new ValidationException('Validation errors occured in ' . $this->getName());

        foreach ($this->behaviours as $behaviour) {
            $behaviour->preValidate($this, $entry, $exception);
        }

        $constraint = $this->getValidationConstraint();
        if ($constraint) {
            $constraint->constrain($entry, $exception);
        }

        foreach ($this->behaviours as $behaviour) {
            $behaviour->postValidate($this, $entry, $exception);
        }

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
            $this->processValidationConstraint($this->validationConstraint);
        }

        return $this->validationConstraint;
    }

    /**
     * Hook to process the validation constraint before it's assigned to this
     * model
     * @param \ride\library\validation\constraint\Constraint $constraint
     * @return null
     */
    protected function processValidationConstraint(Constraint $constraint) {

    }

    /**
     * Saves data to the model
     * @param mixed $entry An entry instance or an array of entry instances
     * @return null
     * @throws \Exception when the entry could not be saved
     */
    public function save($entry) {
        $isTransactionStarted = $this->beginTransaction();

        try {
            if (is_array($entry)) {
                foreach ($entry as $entryValue) {
                    $this->saveEntry($entryValue);
                }
            } else {
                $this->saveEntry($entry);
            }

            $this->commitTransaction($isTransactionStarted);
        } catch (Exception $exception) {
            $this->rollbackTransaction($isTransactionStarted);

            throw $exception;
        }
    }

    /**
     * Saves an entry to the model
     * @param mixed $entry A instance of an entry
     * @return null
     * @throws Exception when the entry could not be saved
     */
    abstract protected function saveEntry($entry);

    /**
     * Deletes data from the model
     * @param mixed $entry An entry instance or an array with entry instances
     * @return mixed Loaded entry or entries which have been deleted
     * @throws \Exception when the entry could not be deleted
     */
    public function delete($entry) {
        $isTransactionStarted = $this->beginTransaction();

        try {
            if (is_array($entry)) {
                foreach ($entry as $entryIndex => $entryValue) {
                    $entry[$entryIndex] = $this->deleteEntry($entryValue);
                }
            } else {
                $entry = $this->deleteEntry($entry);
            }

            $this->commitTransaction($isTransactionStarted);
        } catch (Exception $exception) {
            $this->rollbackTransaction($isTransactionStarted);

            throw $exception;
        }

        return $entry;
    }

    /**
     * Deletes a localized version of a entry from the model
     * @param mixed $entry Entry instance or an array with entry instances
     * @param string $locale Code of the locale
     * @return mixed Loaded entry or entries which have been deleted
     * @throws \Exception when the entry could not be deleted
     */
    public function deleteLocalized($entry, $locale) {
        $isTransactionStarted = $this->beginTransaction();

        try {
            if (is_array($entry)) {
                foreach ($entry as $entryIndex => $entryValue) {
                    $entry[$entryIndex] = $this->deleteLocalizedEntry($entryValue, $locale);
                }
            } else {
                $entry = $this->deleteLocalizedEntry($entry, $locale);
            }

            $this->commitTransaction($isTransactionStarted);
        } catch (Exception $exception) {
            $this->rollbackTransaction($isTransactionStarted);

            throw $exception;
        }

        return $entry;
    }

    /**
     * Deletes an entry from this model
     * @param mixed $entry Entry instance to be deleted
     * @return mixed Loaded entry which has been deleted
     */
    abstract protected function deleteEntry($entry);

    /**
     * Deletes a localized version of an entry from this model
     * @param mixed $entry Entry instance to be deleted
     * @param string $locale Code of the locale
     * @return mixed Loaded entry which has been deleted
     */
    abstract protected function deleteLocalizedEntry($entry, $locale);

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
    protected function getPrimaryKey($entry) {
        if (is_numeric($entry)) {
            return $entry;
        }

        $this->meta->isValidEntry($entry);

        $id = $this->reflectionHelper->getProperty($entry, ModelTable::PRIMARY_KEY);
        if ($id) {
            return $id;
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
        } elseif (!is_string($locale) || $locale == '') {
            throw new OrmException('Provided locale is invalid: ' . $locale);
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
