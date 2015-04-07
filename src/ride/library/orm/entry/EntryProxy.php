<?php

namespace ride\library\orm\entry;

use ride\library\orm\model\Model;

/**
 * Interface for a proxy of an entry
 */
interface EntryProxy extends Entry {

    /**
     * Construct a new entry proxy
     * @param \ride\library\orm\model\Model $model Instance of the User model
     * @param integer $id Id of the entry
     * @param array $properties Values of the known properties
     * @return null
     */
    public function __construct(Model $model, $id, array $properties = array());

    /**
     * Reattaches the ORM to this entry
     * @param \ride\library\orm\OrmManager $orm
     * @return null
     */
    // public function attach(OrmManager $orm);

    /**
     * Detaches the ORM from this entry so serialization becomes possible
     * @return null
     */
    // public function detach();

    /**
     * Gets the loaded value from the data source
     * @param string $fieldName Provide a field name to get only the value of
     * that field
     * @return array|mixed Array with the loaded values if no field provided,
     * value of the field if provided
     */
    public function getLoadedValues($fieldName = null);

    /**
     * Sets the loaded value of the data source
     * @param string|array $fieldName Field name of the provided value or an
     * array with values of multiple fields
     * @param mixed $value Value for the provided field name
     * @return null
     */
    public function setLoadedValues($fieldName, $value = null);

    /**
     * Checks if a the value of a field is loaded
     * @param string $fieldName Name of the field
     * @return boolean True if the value is loaded, false otherwise
     */
    public function isValueLoaded($fieldName);

    /**
     * Checks if a field is set
     * @param string $fieldName Name of the field
     * @return boolean True if the field is set, false otherwise
     */
    public function isFieldSet($fieldName);

}
