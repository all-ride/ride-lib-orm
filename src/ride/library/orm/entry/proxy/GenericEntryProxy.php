<?php

namespace ride\library\orm\entry\proxy;

use ride\library\orm\entry\GenericEntry;
use ride\library\orm\model\Model;

/**
 * Generic proxy for an entry
 */
class GenericEntryProxy extends GenericEntry implements EntryProxy {

    /**
     * Instance of the User model
     * @var \ride\library\orm\model\Model
     */
    private $model;

    /**
     * Initial value state of the entry
     * @var array
     */
    private $_state;

    /**
     * Load state of the entry
     * @var array
     */
    private $_loaded;

    /**
     * Construct a new User entry model
     * @param \ride\library\orm\model\Model $model Instance of the User model
     * @param integer $id Id of the entry
     * @param array $properties Values of the known properties
     * @return null
     */
    public function __construct(Model $model, $id, array $properties = array()) {
        $this->model = $model;
        $this->id = $id;
        $this->_state = array('id' => $id);
        $this->_loaded = array();

        foreach ($properties as $propertyName => $propertyValue) {
            $this->$propertyName = $propertyValue;
            $this->_state[$propertyName] = $propertyValue;
            $this->_loaded[$propertyName] = true;
        }
    }

    /**
     * Sets the state of this entry
     * @param array $state Array with the name of the field as key and the state
     * as value
     * @return null
     */
    public function setEntryState(array $state) {
        $this->_state = $state;
    }

    /**
     * Gets the state of this entry
     * @return array Array with the name of the field as key and the state as
     * value
     */
    public function getEntryState() {
        return $this->_state;
    }

    /**
     * Sets the state of a field of this entry
     * @param string $fieldName Name of the field
     * @param mixed $value State value of the provided field
     * @return null
     */
    public function setFieldState($fieldName, $value) {
        $this->_state[$fieldName] = $value;
    }

    /**
     * Checks if the state of a field is set
     * @param string $fieldName Name of the field
     * @return boolean
     */
    public function hasFieldState($fieldName) {
        return isset($this->_state[$fieldName]);
    }

    /**
     * Gets the state of a field of this entry
     * @param string $fieldName Name of the field
     * @return mixed State value of the provided field
     */
    public function getFieldState($fieldName) {
        if (!$this->hasFieldState($fieldName)) {
            return null;
        }

        return $this->_state[$fieldName];
    }

    /**
     * Sets a property
     * @param string $name Name of the property
     * @param mixed $value Value for the property
     * @return null
     * @todo
     */
    public function __set($name, $value) {

    }

    /**
     * Gets a property
     * @param string $name Name of the property
     * @return mixed Value for the property
     * @todo
     */
    public function __get($name) {

    }

}
