<?php

namespace pallo\library\orm\model\meta;

use pallo\library\database\manipulation\expression\TableExpression;
use pallo\library\database\DatabaseManager;
use pallo\library\orm\definition\field\extended\VersionField;
use pallo\library\orm\definition\field\BelongsToField;
use pallo\library\orm\definition\field\HasField;
use pallo\library\orm\definition\field\HasManyField;
use pallo\library\orm\definition\field\HasOneField;
use pallo\library\orm\definition\field\ModelField;
use pallo\library\orm\definition\field\RelationField;
use pallo\library\orm\definition\ModelTable;
use pallo\library\orm\exception\ModelException;
use pallo\library\orm\exception\OrmException;
use pallo\library\orm\model\LocalizedModel;
use pallo\library\orm\OrmManager;
use pallo\library\validation\constraint\GenericConstraint;
use pallo\library\validation\factory\ValidationFactory;

/**
 * Meta of a model table
 */
class ModelMeta {

    /**
     * Default data class
     * @var string
     */
    const CLASS_DATA = 'pallo\\library\\orm\\model\\data\\Data';

    /**
     * Table definition of the model
     * @var pallo\library\orm\definition\ModelTable
     */
    protected $table;

    /**
     * Class name for data objects of this model
     * @var string
     */
    protected $dataClassName;

    /**
     * Flag to see whether the model table has been parsed
     * @var boolean
     */
    protected $isParsed;

    /**
     * Array with ModelFields objects of the localized fields
     * @var array
     */
    protected $localizedFields;

    /**
     * Array with ModelField objects of the property fields
     * @var array
     */
    protected $properties;

    /**
     * Array with ModelField objects of the belongs to fields
     * @var array
     */
    protected $belongsTo;

    /**
     * Array with ModelField objects of the has one fields
     * @var array
     */
    protected $hasOne;

    /**
     * Array with ModelField objects of the has one fields
     * @var array
     */
    protected $hasMany;

    /**
     * Array with RelationMeta objects
     * @var array
     */
    protected $relations;

    /**
     * Array with the names of the models who have a relation with this model but where there is
     * no relation back.
     * @var array
     */
    protected $unlinkedModels;

    /**
     * Instance of the validation constraint
     * @var pallo\library\validation\constraint\Constraint
     */
    protected $validationConstraint;

    /**
     * Constructs a new model meta definition
     * @param pallo\library\orm\definition\ModelTable $table Table definition of the model
     * @param string $dataClassName Class name for data objects for this model
     * @return null
     * @throws pallo\ZiboException when the data class name is empty or invalid
     */
    public function __construct(ModelTable $table, $dataClassName = null) {
        $this->setDataClassName($dataClassName);

        $this->table = $table;
        $this->unlinkedModels = array();

        $this->isParsed = false;
    }

    /**
     * Return the fields to serialize
     * @return array Array with field names
     */
    public function __sleep() {
        $fields = array(
            'dataClassName',
            'table',
            'isParsed',
        );

        if ($this->unlinkedModels) {
            $fields[] = 'unlinkedModels';
        }

        if (!$this->isParsed) {
            return $fields;
        }

        $fields[] = 'properties';

        $this->properties = array_keys($this->properties);

        if ($this->localizedFields) {
            $fields[] = 'localizedFields';
            $this->localizedFields = array_keys($this->localizedFields);
        }

        if ($this->belongsTo) {
            $fields[] = 'belongsTo';
            $this->belongsTo = array_keys($this->belongsTo);
        }

        if ($this->hasOne) {
            $fields[] ='hasOne';
            $this->hasOne = array_keys($this->hasOne);
        }

        if ($this->hasMany) {
            $fields[] = 'hasMany';
            $this->hasMany = array_keys($this->hasMany);
        }

        if ($this->relations) {
            $fields[] = 'relations';
        }

        return $fields;
    }

    /**
     * Reinitialize the meta after sleeping
     * @return null
     */
    public function __wakeup() {
        if (!isset($this->unlinkedModels)) {
            $this->unlinkedModels = array();
        }

        if (!isset($this->isParsed)) {
            $this->isParsed = false;
        }

        foreach ($this->properties as $index => $fieldName) {
            $this->properties[$fieldName] = $this->table->getField($fieldName);
            unset($this->properties[$index]);
        }

        if (isset($this->localizedFields)) {
            foreach ($this->localizedFields as $index => $fieldName) {
                $this->localizedFields[$fieldName] = $this->table->getField($fieldName);
                unset($this->localizedFields[$index]);
            }
        } else {
            $this->localizedFields = array();
        }

        if (isset($this->belongsTo)) {
            foreach ($this->belongsTo as $index => $fieldName) {
                $this->belongsTo[$fieldName] = $this->table->getField($fieldName);
                unset($this->belongsTo[$index]);
            }
        } else {
            $this->belongsTo = array();
        }

        if (isset($this->hasOne)) {
            foreach ($this->hasOne as $index => $fieldName) {
                $this->hasOne[$fieldName] = $this->table->getField($fieldName);
                unset($this->hasOne[$index]);
            }
        } else {
            $this->hasOne = array();
        }

        if (isset($this->hasMany)) {
            foreach ($this->hasMany as $index => $fieldName) {
                $this->hasMany[$fieldName] = $this->table->getField($fieldName);
                unset($this->hasMany[$index]);
            }
        } else {
            $this->hasMany = array();
        }

        if (!isset($this->relations)) {
            $this->relations = array();
        }
    }

    /**
     * Gets the name of the model
     * @return string
     */
    public function getName() {
        return $this->table->getName();
    }

    /**
     * Sets the class name for data objects of this model
     * @param string $dataClassName
     * @return null
     * @throws pallo\ZiboException when the data class name is empty or invalid
     */
    private function setDataClassName($dataClassName) {
        if ($dataClassName === null) {
            $dataClassName = self::CLASS_DATA;
        }

        if (!is_string($dataClassName) || !$dataClassName) {
            throw new OrmException('Provided data class name is empty');
        }

        $this->dataClassName = $dataClassName;
    }

    /**
     * Gets the class name for data objects of this model
     * @return string
     */
    public function getDataClassName() {
        return $this->dataClassName;
    }

    /**
     * Checks whether the provided data is a valid data object for this model
     * @param mixed $data Data object to check
     * @param boolean $throwException True to throw the exception, false otherwise
     * @return boolean True if the data is valid, false otherwise
     * @throws pallo\library\orm\exception\ModelException when the $throwException flag is false and the data object is not valid
     */
    public function isValidData($data, $throwException = true) {
        $result = $data instanceof $this->dataClassName;

        if ($result || !$throwException) {
            return $result;
        }

        if (is_object($data)) {
            $type = get_class($data);
        } else {
            $type = gettype($data);
        }

        throw new ModelException('Provided ' . $type . ' value is not of the expected ' . $this->dataClassName . ' type');
    }

    /**
     * Gets the validation constraint for this model
     * @return pallo\library\validation\constraint\Constraint|null
     */
    public function getValidationConstraint(ValidationFactory $validationFactory) {
        if ($this->validationConstraint) {
            return $this->validationConstraint;
        }

        $this->validationConstraint = new GenericConstraint();

        $validators = $this->table->getValidators();
        foreach ($validators as $fieldName => $fieldValidators) {
            foreach ($fieldValidators as $validatorName => $validatorOptions) {
                $validator = $validationFactory->createValidator($validatorName, $validatorOptions);

                $this->validationConstraint->addValidator($validator, $fieldName);
            }
        }

        return $this->validationConstraint;
    }

    /**
     * Gets a data format
     * @param string $formatName Name of the format
     * @return string Format string
     */
    public function getDataFormat($formatName) {
        return $this->table->getDataFormat($formatName);
    }

    /**
     * Gets whether this model has localized fields
     * @return boolean True when there are localized fields, false otherwise
     */
    public function isLocalized() {
        return $this->table->isLocalized();
    }

    /**
     * Gets the name of the localized model of this model
     * @return string
     */
    public function getLocalizedModelName() {
        return $this->table->getName() . LocalizedModel::MODEL_SUFFIX;
    }

    /**
     * Gets whether this model will block deletes when a record is still in use by another record
     * @return boolean True to block deletes, false otherwise
     */
    public function willBlockDeleteWhenUsed() {
        return $this->table->willBlockDeleteWhenUsed();
    }

    /**
     * Sets the models who have a relation with this model but where there is no relation back.
     * @param array $unlinkedModels Array with model names
     * @return null
     */
    public function setUnlinkedModels(array $unlinkedModels) {
        $this->unlinkedModels = $unlinkedModels;
    }

    /**
     * Gets the models who have a relation with this model but where there is no relation back.
     * @return array Array with model names
     */
    public function getUnlinkedModels() {
        return $this->unlinkedModels;
    }

    /**
     * Gets the table definition of this model
     * @return pallo\library\orm\definition\ModelTable
     */
    public function getModelTable() {
        return $this->table;
    }

    /**
     * Sets the custom model options
     * @param array $options
     * @return null
     */
    public function setOptions(array $options) {
        $this->table->setOptions($options);
    }

    /**
     * Gets the custom model options
     * @return array
     */
    public function getOptions() {
        return $this->table->getOptions();
    }

    /**
     * Gets a custom model option
     * @param string $name Name of the option
     * @param mixed $default
     * @return mixed
     */
    public function getOption($name, $default = null) {
        return $this->table->getOption($name, $default);
    }

    /**
     * Gets whether this model has a certain field
     * @param string $fieldName Name of the field to check
     * @return boolean True if the model contains the field, false otherwise
     */
    public function hasField($fieldName) {
        return $this->table->hasField($fieldName);
    }

    /**
     * Gets a field from this model
     * @param string $fieldName Name of the field
     * @return pallo\library\orm\definition\field\ModelField
     */
    public function getField($fieldName) {
        return $this->table->getField($fieldName);
    }

    /**
     * Gets all the fields of this model
     * @return array Array with ModelField objects
     */
    public function getFields() {
        return $this->table->getFields();
    }

    /**
     * Gets all the localized fields of this model
     * @return array Array with ModelField objects
     */
    public function getLocalizedFields() {
        if (!$this->isParsed) {
            throw new OrmException('This meta is not parsed, call parseMeta first');
        }

        return $this->localizedFields;
    }

    /**
     * Gets the property fields of this model
     * @return array Array with ModelField objects
     */
    public function getProperties() {
        if (!$this->isParsed) {
            throw new OrmException('This meta is not parsed, call parseMeta first');
        }

        return $this->properties;
    }

    /**
     * Gets the belongs to fields of this model
     * @return array Array with BelongsToField objects
     */
    public function getBelongsTo() {
        if (!$this->isParsed) {
            throw new OrmException('This meta is not parsed, call parseMeta first');
        }

        return $this->belongsTo;
    }

    /**
     * Gets the has one fields of this model
     * @return array Array with HasOneField objects
     */
    public function getHasOne() {
        if (!$this->isParsed) {
            throw new OrmException('This meta is not parsed, call parseMeta first');
        }

        return $this->hasOne;
    }

    /**
     * Gets the has many fields of this model
     * @return array Array with HasManyField objects
     */
    public function getHasMany() {
        if (!$this->isParsed) {
            throw new OrmException('This meta is not parsed, call parseMeta first');
        }

        return $this->hasMany;
    }

    /**
     * Gets the fields with a relation to the provided model
     * @param string $modelName Name of the relation model
     * @param integer $type Type identifier to get only the provided type
     * @return array An array with the fields for the provided type. If no type has been provided, an
     *              array with the type identifier as key and an array with the fields as value
     */
    public function getRelation($modelName, $type = null) {
        return $this->table->getRelationFields($modelName, $type);
    }

    /**
     * Gets whether this model has relation fields or only property fields
     * @return boolean True when the model has relation fields, false otherwise
     */
    public function hasRelations() {
        return $this->table->hasRelationFields();
    }

    /**
     * Gets whether this model has a relation with the provided model
     * @param string $modelName Name of the relation model
     * @param integer $type Type identifier to get only the provided type
     * @return boolean True if there is a field with a relation to the provided model, false otherwise
     */
    public function hasRelationWith($modelName, $type = null) {
        $relations = $this->table->getRelationFields($modelName);

        if ($type !== null) {
            return isset($relations[$type]) && !empty($relations[$type]);
        }

        return !(empty($relations[ModelTable::HAS_MANY]) && empty($relations[ModelTable::HAS_ONE]) && empty($relations[ModelTable::BELONGS_TO]));
    }

    /**
     * Gets whether the relation of the provided field is a relation with the model itself
     * @param string $fieldName Name of the relation field
     * @return boolean True when the relation of the provided field is with the model itself, false otherwise
     * @throws pallo\library\orm\exception\ModelException when no relation meta could be found for the provided field
     */
    public function isRelationWithSelf($fieldName) {
        $relationMeta = $this->getRelationMeta($fieldName);
        return $relationMeta->isRelationWithSelf();
    }

    /**
     * Gets the model name of the provided relation field
     * @param string $fieldName Name of the field
     * @return string
     */
    public function getRelationModelName($fieldName) {
        $field = $this->table->getField($fieldName);

        if (!($field instanceof RelationField)) {
            throw new ModelException($fieldName . ' is not a relation field');
        }

        return $field->getRelationModelName();
    }

    /**
     * Gets the link model for the provided relation field
     * @param string $fieldName Name of the relation field
     * @return null|pallo\library\orm\model\Model The link model if set, null otherwise
     * @throws pallo\library\orm\exception\ModelException when no relation meta could be found for the provided field
     */
    public function getRelationLinkModelName($fieldName) {
        $relationMeta = $this->getRelationMeta($fieldName);
        return $relationMeta->getLinkModelName();
    }

    /**
     * Gets the foreign key for the provided relation field
     * @param string $fieldName Name of the relation field
     * @return null|pallo\library\orm\definition\field\ModelField
     * @throws pallo\library\orm\exception\ModelException when no relation meta could be found for the provided field
     */
    public function getRelationForeignKey($fieldName) {
        $relationMeta = $this->getRelationMeta($fieldName);
        return $relationMeta->getForeignKey();
    }

    /**
     * Gets the foreign key with the link model for the provided relation field
     * @param string $fieldName Name of the relation field
     * @return null|pallo\library\orm\definition\field\ModelField
     * @throws pallo\library\orm\exception\ModelException when no relation meta could be found for the provided field
     */
    public function getRelationForeignKeyToSelf($fieldName) {
        $relationMeta = $this->getRelationMeta($fieldName);
        return $relationMeta->getForeignKeyToSelf();
    }

    /**
     * Gets whether the relation of the provided field is a many to many relation
     * @param string $fieldName Name of the relation field
     * @return boolean True if the relation is a many to many relation, false otherwise
     * @throws pallo\library\orm\exception\ModelException when no relation meta could be found for the provided field
     */
    public function isHasManyAndBelongsToMany($fieldName) {
        $relation = $this->getRelationMeta($fieldName);
        return $relation->isHasManyAndBelongsToMany();
    }

    /**
     * Gets the order statement string for the relation of the provided has many field
     * @param string $fieldName Name of the has many field
     * @return null|string
     */
    public function getRelationOrder($fieldName) {
        $field = $this->table->getField($fieldName);

        if (!($field instanceof HasManyField)) {
            throw new ModelException($fieldName . ' is not a has many field');
        }

        return $field->getRelationOrder();
    }

    /**
     * Gets the relation meta for the provided relation field
     * @param string $fieldName Name of the relation field
     * @return RelationMeta
     * @throws pallo\library\orm\exception\ModelException when no relation meta could be found for the provided field
     */
    private function getRelationMeta($fieldName) {
        if (!$this->isParsed) {
            throw new OrmException('This meta is not parsed, call parseMeta first');
        }

        if (!isset($this->relations[$fieldName])) {
            throw new ModelException('Could not find the relation meta of ' . $fieldName);
        }

        return $this->relations[$fieldName];
    }

    /**
     * Checks if the meta has been parsed
     * @return boolean
     */
    public function isParsed() {
        return $this->isParsed;
    }

    /**
     * Makes sure the model table is parsed so the data for the methods of this object are initialized
     * @return null
     */
    public function parseMeta(OrmManager $orm) {
        if ($this->isParsed) {
            return;
        }

        $this->localizedFields = array();
        $this->properties = array();
        $this->belongsTo = array();
        $this->hasOne = array();
        $this->hasMany = array();
        $this->relations = array();

        $fields = $this->table->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($field->isLocalized()) {
                $this->localizedFields[$fieldName] = $field;
            }

            if ($field instanceof HasField) {
                $this->parseHasField($field, $orm);
            } elseif ($field instanceof BelongsToField) {
                $this->parseBelongsToField($field, $orm);
            } else {
                $this->parsePropertyField($field);
            }
        }

        $this->isParsed = true;
    }

    /**
     * Parses a has field into the meta
     * @param pallo\library\orm\definition\field\HasField $field
     * @param pallo\library\orm\OrmManager $orm Instance of the model manager
     * @return null
     */
    private function parseHasField(HasField $field, OrmManager $orm) {
        $name = $field->getName();
        $modelName = $this->table->getName();
        $relationModelName = $field->getRelationModelName();

        try {
            $relationModel = $orm->getModel($relationModelName);
        } catch (OrmException $exception) {
            throw new ModelException('No relation model found for field ' . $name . ' in ' . $modelName);
        }

        $relation = new RelationMeta();
        $relation->setIsRelationWithSelf($relationModelName == $modelName);

        $linkModelName = $field->getLinkModelName();
        if ($field->isLocalized()) {
            $localizedModel = $orm->getModel($this->getLocalizedModelName());
            $localizedField = $localizedModel->getMeta()->getField($name);

            $linkModelName = $localizedField->getLinkModelName();

            $field->setLinkModelName($linkModelName);
            $relation->setLinkModelName($linkModelName);
        } elseif ($linkModelName) {
            $relation->setLinkModelName($linkModelName);

            $linkModel = $orm->getModel($linkModelName);
            $linkModelTable = $linkModel->getMeta()->getModelTable();

            if (!$relation->isRelationWithSelf()) {
                $relation->setForeignKey($this->getForeignKey($linkModelTable, $relationModelName)->getName());
                $relation->setForeignKeyToSelf($this->getForeignKey($linkModelTable, $modelName)->getName());
            } else {
                 $foreignKeys = $this->getForeignKeys($linkModelTable, $modelName);
                 foreach ($foreignKeys as $foreignKey => $null) {
                     $foreignKeys[$foreignKey] = $foreignKey;
                 }

                 $relation->setForeignKey($foreignKeys);
            }
        } else {
            $relationModelTable = $relationModel->getMeta()->getModelTable();
            $foreignKey = $field->getForeignKeyName();

            $relation->setForeignKey($this->getForeignKey($relationModelTable, $modelName, $foreignKey)->getName());
        }

        $this->relations[$name] = $relation;

        if ($field instanceof HasOneField) {
            $this->hasOne[$name] = $field;
        } else {
            if ($linkModelName) {
                $relation->setIsHasManyAndBelongsToMany(true);
            }

            $this->hasMany[$name] = $field;
        }
    }

    /**
     * Parses a belongs to field in the meta
     * @param pallo\library\orm\definition\field\BelongsToField $field
     * @return null
     * @param pallo\library\orm\OrmManager $orm Instance of the model manager
     */
    private function parseBelongsToField(BelongsToField $field, OrmManager $orm) {
        $name = $field->getName();
        $modelName = $this->getName();
        $relationModelName = $field->getRelationModelName();

        try {
            $relationModel = $orm->getModel($relationModelName);
        } catch (OrmException $exception) {
            throw new ModelException('Relation model ' . $relationModelName . ' not found for field ' . $name . ' in model ' . $modelName);
        }

        $relation = new RelationMeta();
        $relation->setIsRelationWithSelf($relationModelName == $modelName);

        $this->relations[$name] = $relation;
        $this->belongsTo[$name] = $field;

        $relationFields = $relationModel->getMeta()->getRelation($modelName);

        if (!$relationFields[ModelTable::BELONGS_TO] && !$relationFields[ModelTable::HAS_MANY] && !$relationFields[ModelTable::HAS_ONE]) {
            return;
        }

        if ($relationFields[ModelTable::HAS_MANY]) {
            $relationType = ModelTable::HAS_MANY;
        } else if ($relationFields[ModelTable::HAS_ONE]) {
            $relationType = ModelTable::HAS_ONE;
        } else {
            return;
        }

        $relationField = array_shift($relationFields[$relationType]);
        if ($relationField->isLocalized()) {
            $linkModelName = $field->getLinkModelName();
            if (empty($linkModelName)) {
            	throw new ModelException('No link model found for field ' . $name . ' in ' . $modelName);
            }

            $relation->setLinkModelName($linkModelName);
        }
    }

    /**
     * Parses a property field in the meta
     * @param pallo\library\orm\definition\field\ModelField $field
     * @return null
     */
    private function parsePropertyField(ModelField $field) {
        $this->properties[$field->getName()] = $field;
    }

    /**
     * Gets the foreign key from the provided model table for the provided
     * relation model
     * @param pallo\library\orm\definition\ModelTable $modelTable Table
     * definition of the model
     * @param string $relationModelName Model name to get the foreign keys of
     * @param string $foreignKey Name of the foreign key
     * @return array Array with ModelField objects
     * @throws pallo\library\orm\exception\ModelException when the provided
     * foreign key is not found in the model table
     * @throws pallo\library\orm\exception\ModelException when there are
     * multiple foreign keys
     */
    private function getForeignKey(ModelTable $modelTable, $relationModelName, $foreignKey = null) {
        $foreignKeys = $this->getForeignKeys($modelTable, $relationModelName);

        if ($foreignKey) {
            if (isset($foreignKeys[$foreignKey])) {
                return $foreignKeys[$foreignKey];
            }

            throw new ModelException('Foreign key ' . $foreignKey . ' not found in ' . $relationModelName);
        }

        if (count($foreignKeys) == 1) {
            return array_pop($foreignKeys);
        }

        throw new ModelException('There are multiple relations with ' . $relationModelName . '. Please define a foreign key.');
    }

    /**
     * Gets the foreign keys from the provided model table for the provided
     * relation model. When no foreign keys are found and the relation model
     * is a localized model, the unlocalized model will be queried for the
     * foreign keys.
     * @param pallo\library\orm\definition\ModelTable $modelTable Table
     * definition of the model
     * @param string $relationModelName Model name to get the foreign keys of
     * @return array Array with ModelField objects
     * @throws pallo\library\orm\exception\ModelException when there are no
     * foreign keys found the provided model
     */
    private function getForeignKeys(ModelTable $modelTable, $relationModelName) {
        if (!$relationModelName) {
            throw new ModelException('Provided relation model name is empty');
        }

        $foreignKeys = $modelTable->getRelationFields($relationModelName, ModelTable::BELONGS_TO);

        if (!$foreignKeys) {
            if (preg_match('/' . LocalizedModel::MODEL_SUFFIX . '$/', $relationModelName)) {
                $relationModelName = substr($relationModelName, 0, strlen(LocalizedModel::MODEL_SUFFIX) * -1);
                $foreignKeys = $modelTable->getRelationFields($relationModelName, ModelTable::BELONGS_TO);
            }

            if (!$foreignKeys) {
                throw new ModelException('No foreign key found for ' . $relationModelName . ' found in ' . $modelTable->getName());
            }
        }

        return $foreignKeys;
    }

}