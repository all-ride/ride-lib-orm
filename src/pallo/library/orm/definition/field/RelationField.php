<?php

namespace pallo\library\orm\definition\field;

use pallo\library\database\definition\Field;
use pallo\library\orm\exception\ModelException;

/**
 * Abstract definition of a relation field of a model
 */
abstract class RelationField extends ModelField {

    /**
     * Name of the model for which this field defines a relation
     * @var string
     */
    protected $relationModelName;

    /**
     * Flag to see if the data of the relation model is dependant on this model
     * @var boolean
     */
    protected $isDependant;

    /**
     * Optional name of the model which is needed to link with the relation model
     * @var string
     */
    protected $linkModelName;

    /**
     * Name of the foreign key in the relation model
     * @var string
     */
    protected $foreignKeyName;

    /**
     * Construct this relation field
     * @param string $name name of the field
     * @param string $relationModelName name of the model for which this field defines a relation
     * @return null
     */
    public function __construct($name, $relationModelName) {
        parent::__construct($name, Field::TYPE_FOREIGN_KEY);

        $this->setRelationModelName($relationModelName);
    }

    /**
     * Returns the fields to serialize
     * @return array Array with field names
     */
    public function __sleep() {
        $fields = parent::__sleep();

        $fields[] = 'relationModelName';

        if ($this->isDependant) {
            $fields[] = 'isDependant';
        }

        if ($this->linkModelName) {
            $fields[] = 'linkModelName';
        }

        if ($this->foreignKeyName) {
            $fields[] = 'foreignKeyName';
        }

        return $fields;
    }

    /**
     * Reinitializes this field after sleeping
     * @return null
     */
    public function __wakeup() {
        if (!$this->isDependant) {
            $this->isDependant = false;
        }
    }

    /**
     * Set the name of the model for which this field defines a relation
     * @param string $name name of the model
     * @return null
     * @throws pallo\ZiboException when the provided name is not a string
     * @throws pallo\library\orm\exception\ModelException when the provided name is empty
     */
    private function setRelationModelName($name) {
        if (!is_string($name) || !$name) {
            throw new ModelException('Provided name of the relation model is empty');
        }

        $this->relationModelName = $name;
    }

    /**
     * Get the name of the model for which this field defines a relation
     * @return string
     */
    public function getRelationModelName() {
        return $this->relationModelName;
    }

    /**
     * Set the dependency of the relation model
     * @param boolean $isDependant true to delete the relations when the the main object is deleted, false otherwise
     * @return null
     */
    public function setIsDependant($isDependant) {
        $this->isDependant = $isDependant;
    }

    /**
     * Get the dependency of the relation model
     * @return boolean true to delete the relations of this field
     */
    public function isDependant() {
        return $this->isDependant;
    }

    /**
     * Set the name of the link model needed for this relation
     * @param string $modelName name of the link model
     * @return null
     */
    public function setLinkModelName($modelName) {
        if (is_null($modelName)) {
            $this->linkModelName = null;
            return;
        }

        if (!is_string($modelName) || !$modelName) {
            throw new ModelException('Provided name of the link model is empty');
        }

        $this->linkModelName = $modelName;
    }

    /**
     * Get the name of the link model needed for this relation
     * @return string
     */
    public function getLinkModelName() {
        return $this->linkModelName;
    }

    /**
     * Sets the name of the foreign key in the relation model
     * @param string $foreignKey Name of the foreign key field
     * @return null
     */
    public function setForeignKeyName($foreignKey) {
        $this->foreignKeyName = $foreignKey;
    }

    /**
     * Gets the name of the foreign key in the relation model
     * @return string
     */
    public function getForeignKeyName() {
        return $this->foreignKeyName;
    }

}