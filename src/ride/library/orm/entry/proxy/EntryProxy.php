<?php

namespace ride\library\orm\entry\proxy;

use ride\library\orm\model\Model;

/**
 * Interface for an entry proxy
 */
interface EntryProxy {

    /**
     * Construct a new entry proxy
     * @param \ride\library\orm\model\Model $model Instance of the User model
     * @param integer $id Id of the entry
     * @param array $properties Values of the known properties
     * @return null
     */
    public function __construct(Model $model, $id, array $properties = array());

    /**
     * Gets whether this entry is clean and not modified
     * @return boolean
     */
    public function hasCleanState();

    /**
     * Sets the state of this entry
     * @param array $state Array with the name of the field as key and the state
     * as value
     * @return null
     */
    public function setEntryState(array $state);

    /**
     * Gets the state of this entry
     * @return array Array with the name of the field as key and the state as
     * value
     */
    public function getEntryState();

    /**
     * Sets the state of a field of this entry
     * @param string $fieldName Name of the field
     * @param mixed $value State value of the provided field
     * @return null
     */
    public function setFieldState($fieldName, $value);

    /**
     * Checks if the state of a field is set
     * @param string $fieldName Name of the field
     * @return boolean
     */
    public function hasFieldState($fieldName);

    /**
     * Gets the state of a field of this entry
     * @param string $fieldName Name of the field
     * @return mixed State value of the provided field
     */
    public function getFieldState($fieldName);

    /**
     * Checks if a field has been loaded
     * @param string $fieldName Name of the field
     * @return boolean
     */
    public function isFieldLoaded($fieldName);

}
