<?php

namespace ride\library\orm\definition;

use ride\library\database\definition\Field;
use ride\library\database\definition\ForeignKey;
use ride\library\database\definition\Index;
use ride\library\database\definition\Table;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\HasField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\HasOneField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\exception\ModelException;
use ride\library\orm\exception\OrmException;
use ride\library\orm\model\data\format\EntryFormat as Format;
use ride\library\orm\model\data\format\EntryFormatter;
use ride\library\validation\constraint\Constraint;

/**
 * Definition of a Model
 */
class ModelTable {

    /**
     * Type identifier for a property field
     * @var integer
     */
    const PROPERTY = 1;

    /**
     * Type identifier for a belongs to field
     * @var integer
     */
    const BELONGS_TO = 2;

    /**
     * Type identifier for a has one field
     * @var integer
     */
    const HAS_ONE = 3;

    /**
     * Type identifier for a has many field
     * @var integer
     */
    const HAS_MANY = 4;

    /**
     * Name of the primary key field
     * @var string
     */
    const PRIMARY_KEY = 'id';

    /**
     * Regular expression for the model name
     * @var string
     */
    const REGEX_NAME = '/^([a-zA-Z0-9_]){3,}$/';

    /**
     * Name of the model
     * @var string
     */
    private $name;

    /**
     * Flag to see if this model has localized fields
     * @var boolean
     */
    private $isLocalized;

    /**
     * The fields of this model
     * @var array
     */
    private $fields;

    /**
     * The indexes of this model
     * @var array
     */
    private $indexes;

    /**
     * Validation constraint for the data
     * @var \ride\library\validation\constraint\Constraint
     */
    private $constraint;

    /**
     * Array with formats to generate a string representation of a the entries
     * @var array
     */
    private $formats;

    /**
     * Flag to see if deletes should be blocked when a record is still linked by
     * another model
     * @var boolean
     */
    private $willBlockDeleteWhenUsed;

    /**
     * Custom options for this model
     * @var array
     */
    private $options;

    /**
     * Construct this model definition
     * @param string $name name of the model
     * @return null
     */
    public function __construct($name) {
        $this->setName($name);

        $this->isLocalized = false;
        $this->fields = array();
        $this->indexes = array();
        $this->constraint = null;
        $this->formats = array();
        $this->willBlockDeleteWhenUsed = false;
        $this->options = array();

        $primaryKey = new PropertyField(self::PRIMARY_KEY, Field::TYPE_PRIMARY_KEY);
        $primaryKey->setIsAutoNumbering(true);
        $primaryKey->setIsPrimaryKey(true);

        $this->addField($primaryKey);
    }

    /**
     * Return the fields to serialize
     * @return array Array with field names
     */
    public function __sleep() {
        $fields = array('name', 'fields');

        if ($this->isLocalized) {
            $fields[] = 'isLocalized';
        }

        if ($this->indexes) {
            $fields[] = 'indexes';
        }

        if ($this->constraint) {
            $fields[] = 'constraint';
        }

        if ($this->formats) {
            $fields[] = 'formats';
        }

        if ($this->willBlockDeleteWhenUsed) {
            $fields[] = 'willBlockDeleteWhenUsed';
        }

        if ($this->options) {
            $fields[] = 'options';
        }

        return $fields;
    }

    /**
     * Reinitialize this object after unserializing
     * @return null
     */
    public function __wakeup() {
        if (!$this->indexes) {
            $this->indexes = array();
        }

        if (!$this->formats) {
            $this->formats = array();
        }

        if (!$this->options) {
            $this->options = array();
        }
    }

    /**
     * Sets the name of this model
     * @param string $name
     * @return null
     * @throws \ride\library\orm\exception\ModelException when the name is empty or invalid
     */
    private function setName($name) {
        if (!is_string($name) || !$name) {
            throw new ModelException('Provided name is empty');
        }

        $this->name = $name;
    }

    /**
     * Get the name of this model
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Sets the extra options of this model
     * @param array $options
     * @return null
     */
    public function setOptions(array $options) {
        $this->options = $options;
    }

    /**
     * Sets an extra option to this model
     * @param string $name Name of the option
     * @param mixed $value Value for the option
     * @return null
     */
    public function setOption($name, $value) {
        $this->options[$name] = $value;
    }

    /**
     * Gets the extra options of this model
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * Gets a extra options of this model
     * @param string $name Name of the option
     * @param mixed $default Value to be returned when the option is not set
     * @return mixed
     */
    public function getOption($name, $default = null) {
        if (!isset($this->options[$name])) {
            return $default;
        }

        return $this->options[$name];
    }

    /**
     * Gets the is localized flag
     * @return boolean
     */
    public function isLocalized() {
        return $this->isLocalized;
    }

    /**
     * Sets whether this model will block deletes when a record is still in use by another record
     * @param boolean $flag True to block deletes, false otherwise
     * @return null
     */
    public function setWillBlockDeleteWhenUsed($flag) {
        $this->willBlockDeleteWhenUsed = $flag;
    }

    /**
     * Gets whether this model will block deletes when a record is still in use by another record
     * @return boolean True to block deletes, false otherwise
     */
    public function willBlockDeleteWhenUsed() {
        return $this->willBlockDeleteWhenUsed;
    }

    /**
     * Get the database table definition of this model
     * @return \ride\library\database\definition\Table
     */
    public function getDatabaseTable() {
        $table = new Table($this->name);

        foreach ($this->fields as $fieldName => $field) {
            if ($field->isLocalized() || $field instanceof HasManyField || $field instanceof HasOneField) {
                continue;
            }

            $table->addField($field);

            if ($field instanceof BelongsToField) {
                $name = $this->name . '_' . ucfirst($fieldName);
                if (strlen($name) > 64) {
                    $name = '_' . ucfirst($fieldName);
                    $name = substr($this->name, 0, 64 - strlen($name)) . $name;
                }

                $foreignKey = new ForeignKey($fieldName, $field->getRelationModelName(), self::PRIMARY_KEY, $name);
                $table->setForeignKey($foreignKey);
            }
        }

        foreach ($this->indexes as $index) {
            $fields = $index->getFields();
            foreach ($fields as $field) {
                if ($field->isLocalized()) {
                    continue 2;
                }
            }

            $table->addIndex($index);
        }

        return $table;
    }

    /**
     * Adds a field to this model
     * @param \ride\library\orm\definition\field\ModelField $field
     * @return null
     * @throws \ride\library\orm\exception\ModelException when the name of the field is already set in this model
     * @throws \ride\library\orm\exception\ModelException when the field has the same link as another field in this model
     */
    public function addField(ModelField $field) {
        if (isset($this->fields[$field->getName()])) {
            throw new ModelException($field->getName() . ' is already set');
        }

        $this->setField($field);
    }

    /**
     * Sets a field to this model
     * @param \ride\library\orm\definition\field\ModelField $field
     * @return null
     * @throws \ride\library\orm\exception\ModelException when the field has the same link as another field in this model
     */
    public function setField(ModelField $field) {
        if ($field->isLocalized() && !$this->isLocalized) {
            $this->isLocalized = true;
        }

        $name = $field->getName();

        $addIndex = false;

        if ($field instanceof BelongsToField) {
            $addIndex = true;
        } elseif ($field instanceof HasField) {
            if ($field instanceof HasOneField) {
                $type = self::HAS_ONE;
            } else {
                $type = self::HAS_MANY;
            }

            $relationFields = $this->getRelationFields($field->getRelationModelName(), $type);

            $numRelationFields = count($relationFields);
            if ($numRelationFields > 0) {
                $linkModelName = $field->getLinkModelName();
                if ($linkModelName) {
                    foreach ($relationFields as $relationFieldName => $relationField) {
                        if ($relationFieldName == $name) {
                            continue;
                        }

                        if ($relationField->getLinkModelName() == $linkModelName) {
                            throw new ModelException('Can\'t add ' . $name . ' to ' . $this->name . ': ' . $field->getRelationModelName() . ' is already linked with link model ' . $linkModelName . ' through the ' . $relationFieldName . ' field, check the link models.');
                        }
                    }
                }
            }
        }

        $this->fields[$name] = $field;

        if ($addIndex && !$this->hasIndex($name)) {
            $index = new Index($name, array($field));
            $this->addIndex($index);
        }
    }

    /**
     * Removes a field from this model
     * @param string $name Name of the field
     * @return null
     * @throws \ride\library\orm\exception\ModelException when the provided name is empty or invalid
     * @throws \ride\library\orm\exception\ModelException when the field is not in this model
     */
    public function removeField($name) {
        $field = $this->getField($name);

        unset($this->fields[$name]);

        foreach ($this->indexes as $indexName => $index) {
            $removeIndex = false;

            $indexFields = $index->getFields();
            foreach ($indexFields as $indexField) {
                if ($indexField->getName() == $name) {
                    $removeIndex = true;
                    break;
                }
            }

            if ($removeIndex) {
                unset($this->indexes[$indexName]);
            }
        }

        if (!$this->isLocalized || ($this->isLocalized && !$field->isLocalized())) {
            return;
        }

        $this->isLocalized = false;

        foreach ($this->fields as $field) {
            if (!$field->isLocalized()) {
                continue;
            }

            $this->isLocalized = true;

            break;
        }
    }

    /**
     * Checks whether this model has a field
     * @param string $name Name of the field
     * @return boolean True if this model has the provided field, false otherwise
     * @throws \ride\library\orm\exception\ModelException when the provided name is empty or invalid
     */
    public function hasField($name) {
        if (!is_string($name) || !$name) {
            throw new ModelException('Provided field name is empty');
        }

        return isset($this->fields[$name]);
    }

    /**
     * Gets a field by name
     * @param string $name Name of the field
     * @return \ride\library\orm\definition\field\ModelField
     * @throws \ride\library\orm\exception\ModelException when the field is not in this model
     */
    public function getField($name) {
        if (!$this->hasField($name)) {
            throw new ModelException('Field ' . $name . ' not found in ' . $this->name);
        }

        return $this->fields[$name];
    }

    /**
     * Gets all the fields of this model
     * @return array Array with the name of the field as key and the ModelField object as value
     */
    public function getFields() {
        return $this->fields;
    }

    /**
     * Order the field names in the order of the provided array
     * @param array $fieldNames Array with the new order of field names
     * @return null
     */
    public function orderFields(array $fieldNames) {
        $currentFields = $this->fields;

        $fields = array();
        $fields[self::PRIMARY_KEY] = $this->fields[self::PRIMARY_KEY];
        unset($this->fields[self::PRIMARY_KEY]);

        foreach ($fieldNames as $fieldName) {
            if ($fieldName == self::PRIMARY_KEY) {
                continue;
            }

            if (!$this->hasField($fieldName)) {
                $this->fields = $currentFields;

                throw new ModelException('Field ' . $fieldName . ' not found in ' . $this->name);
            }

            $fields[$fieldName] = $this->fields[$fieldName];

            unset($this->fields[$fieldName]);
        }

        foreach ($this->fields as $fieldName => $field) {
            $fields[$fieldName] = $field;
        }

        $this->fields = $fields;
    }

    /**
     * Checks whether this model table has relation fields
     * @return boolean True when the model has relation fields, false otherwise
     */
    public function hasRelationFields() {
        foreach ($this->fields as $field) {
            if ($field instanceof RelationField) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the fields with a relation to the provided model
     * @param string $modelName Name of the relation model
     * @param integer $type Type identifier to get only the provided type
     * @return array An array with the fields for the provided type. If no type has been provided, an
     *              array with the type identifier as key and an array with the fields as value
     */
    public function getRelationFields($modelName, $type = null) {
        $result = array(
            self::BELONGS_TO => array(),
            self::HAS_ONE => array(),
            self::HAS_MANY => array(),
        );

        foreach ($this->fields as $fieldName => $field) {
            if (!($field instanceof RelationField)) {
                continue;
            }

            if ($field->getRelationModelName() != $modelName) {
                continue;
            }

            if ($field instanceof BelongsToField) {
                $result[self::BELONGS_TO][$fieldName] = $field;
                continue;
            }
            if ($field instanceof HasOneField) {
                $result[self::HAS_ONE][$fieldName] = $field;
                continue;
            }
            if ($field instanceof HasManyField) {
                $result[self::HAS_MANY][$fieldName] = $field;
                continue;
            }
        }

        if (!$type) {
            return $result;
        }

        if (!isset($result[$type])) {
            throw new OrmException('Provided type is not a valid relation type, use BELONGS_TO, HAS_ONE or HAS_MANY');
        }

        return $result[$type];
    }

    /**
     * Adds an index to the table
     * @param \ride\library\database\definition\Index $index index to add to the table
     * @return null
     * @throws \ride\library\orm\exception\ModelException when a field of the index is not in this table
     * @throws \ride\library\orm\exception\ModelException when a field of the index is not a property or a belongs to field
     */
    public function addIndex(Index $index) {
        if ($this->hasIndex($index->getName())) {
            throw new ModelException('Index ' . $index->getName() . ' is already set in this table');
        }

        $this->setIndex($index);
    }

    /**
     * Sets an index to the table
     * @param \ride\library\database\definition\Index $index index to add to the table
     * @return null
     * @throws \ride\library\orm\exception\ModelException when a field of the index is not in this table
     * @throws \ride\library\orm\exception\ModelException when a field of the index is not a property or a belongs to field
     * @throws \ride\library\orm\exception\ModelException when the index contains out of localized and unlocalized fields
     */
    public function setIndex(Index $index) {
        $isLocalized = null;

        $fields = $index->getFields();
        foreach ($fields as $fieldName => $field) {
            if (!$this->hasField($fieldName)) {
                throw new ModelException('Cannot add the index: the field ' . $fieldName . ' is not set in this table');
            }

            if ($this->fields[$fieldName] instanceof HasField) {
                throw new ModelException('Cannot add the index: the field ' . $fieldName . ' is not a property or a belongs to field');
            }

            if ($isLocalized === null) {
                $isLocalized = $field->isLocalized();
            } elseif ($field->isLocalized() != $isLocalized) {
                throw new ModelException('Cannot combine localized and unlocalized fields in 1 index');
            }
        }

        $this->indexes[$index->getName()] = $index;
    }

    /**
     * Gets the index definition of an index
     * @param string $name of the index
     * @return \ride\library\database\definition\Index index definition of the index
     * @throws \ride\library\orm\exception\ModelException when no valid string provided as name
     * @throws \ride\library\orm\exception\ModelException when the name is empty or the index does not exist
     */
    public function getIndex($name) {
        if (!$this->hasIndex($name)) {
            throw new ModelException('Index ' . $name . ' is not defined for this table');
        }

        return $this->indexes[$name];
    }

    /**
     * Gets the indexes of this table
     * @return array array with Index objects as value and the indexname as key
     */
    public function getIndexes() {
        return $this->indexes;
    }

    /**
     * Checks whether this table has a certain index
     * @param string $name name of the index
     * @throws \ride\library\orm\exception\ModelException when no valid string provided as name
     * @throws \ride\library\orm\exception\ModelException when the name is empty
     * @throws \ride\library\orm\exception\ModelException when the name is invalid
     */
    public function hasIndex($name) {
        if (!is_string($name) || $name == '') {
            throw new ModelException('Provided name is empty');
        }

        return isset($this->indexes[$name]);
    }

    /**
     * Removes a index from this model
     * @param string $name Name of the index
     * @return null
     * @throws \ride\library\orm\exception\ModelException when the provided name is empty or invalid
     * @throws \ride\library\orm\exception\ModelException when the index is not in this model
     */
    public function removeIndex($name) {
        $index = $this->getIndex($name);

        unset($this->indexes[$name]);
    }

    /**
     * Gets the fields which are possible to use in an index
     * @return array Array with the field name as key and value
     */
    public function getIndexFields() {
        $indexFields = array();

        foreach ($this->fields as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY) {
                continue;
            }

            if ($field instanceof PropertyField || $field instanceof BelongsToField) {
                $indexFields[$fieldName] = $fieldName;
            }
        }

        return $indexFields;
    }

    /**
     * Gets the filters of all the fields
     * @return array Array with the field name as key and an array with
     * filter definitions as value
     */
    public function getFilters() {
        $filters = array();
        foreach ($this->fields as $fieldName => $field) {
            $filters[$fieldName] = $field->getFilters();
        }

        return $filters;
    }

    /**
     * Gets the validators of all the fields
     * @return array Array with the field name as key and an array with
     * validator definitions as value
     */
    public function getValidators() {
        $validators = array();
        foreach ($this->fields as $fieldName => $field) {
            $validators[$fieldName] = $field->getValidators();
        }

        return $validators;
    }

    /**
     * Sets the validation constraint for this model
     * @param \ride\library\validation\constraint\Constraint $constraint
     * @return null
     */
    public function setValidationConstraint(Constraint $constraint) {
        $this->constraint = $constraint;
    }

    /**
     * Gets the validation constraint for this model
     * @return \ride\library\validation\constraint\Constraint|null
     */
    public function getValidationConstraint() {
        return $this->constraint;
    }

    /**
     * Adds a entry format
     * @param string $name Name of the format
     * @param string $format Format string
     * @return null
     */
    public function setFormat($name, $format) {
        if (!is_string($name) || !$name) {
            throw new ModelException('Provided name is empty');
        }

        if (!is_string($format) || !$format) {
            throw new ModelException('Provided format is empty');
        }

        $this->formats[$name] = $format;
    }

    /**
     * Gets a format string
     * @param string $name Name of the format
     * @param boolean $throwException Set to true to throw an exception when
     * the data format does not exist
     * @return string|null Format for the provided name or null when it's not
     *  set
     * @throws \ride\library\orm\exception\ModelException when there is no
     * format set with the provided name and $throwException is true
     */
    public function getFormat($name, $throwException = true) {
        if ($this->hasFormat($name)) {
            return $this->formats[$name];
        }

        if ($throwException) {
            throw new ModelException('No format set with name ' . $name);
        }

        return null;
    }

    /**
     * Checks if this model has a certain format
     * @param string $name Name of the format
     * @return boolean True if this table has a format by the provided name,
     * false otherwise
     * @throws \ride\library\orm\exception\ModelException when the provided name
     * is empty or not a string
     */
    public function hasFormat($name) {
        if (!is_string($name) || !$name) {
            throw new ModelException('Provided name is empty');
        }

        return isset($this->formats[$name]);
    }

    /**
     * Removes a format
     * @param string $name Name of the format
     * @return null
     * @throws \ride\library\orm\exception\ModelException
     */
    public function removeFormat($name) {
        if (!$this->hasFormat($name)) {
            throw new ModelException('Could not remove format: none set with name ' . $name);
        }

        unset($this->formats[$name]);
    }

    /**
     * Gets all the formats
     * @return array Array with the name of the format as key and the format
     * string as value
     */
    public function getFormats() {
        return $this->formats;
    }

}
