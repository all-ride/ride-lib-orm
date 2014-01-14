<?php

namespace pallo\library\orm\definition\field;

use pallo\library\database\definition\Field;
use pallo\library\orm\definition\FieldValidator;
use pallo\library\orm\exception\OrmException;

/**
 * Base field definition for a model table
 */
abstract class ModelField extends Field {

    /**
     * Regular expression for the model name
     * @var string
     */
    const REGEX_NAME = '/^([a-zA-Z0-9]){3,}$/';

    /**
     * flag to see if this field is localized
     * @var boolean
     */
    protected $isLocalized = false;

    /**
     * Custom options for this field
     * @var array
     */
    protected $options = array();

    /**
     * Returns the fields to serialize
     * @return array Array with field names
     */
    public function __sleep() {
        $fields = parent::__sleep();

        if ($this->isLocalized) {
            $fields[] = 'isLocalized';
        }

        if ($this->options) {
            $fields[] = 'options';
        }

        return $fields;
    }

    /**
     * Set whether this field is localized or not
     * @param boolean $isLocalized
     * @return null
     */
    public function setIsLocalized($isLocalized) {
        $this->isLocalized = $isLocalized;
    }

    /**
     * Check whether this field is localized
     * @return boolean
     */
    public function isLocalized() {
        return $this->isLocalized;
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

}