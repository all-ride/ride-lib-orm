<?php

namespace ride\library\orm\definition\field;

use ride\library\database\definition\Field;
use ride\library\orm\definition\FieldValidator;

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
     * Filters for this field
     * @var array
     */
    protected $filters = array();

    /**
     * Validators for this field
     * @var array
     */
    protected $validators = array();

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

        if ($this->filters) {
            $fields[] = 'filters';
        }

        if ($this->validators) {
            $fields[] = 'validators';
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
     * Adds a filter definition
     * @param string $name Name of the filter
     * @param array $options Options for the filter
     * @return null
     */
    public function addFilter($name, array $options) {
        $this->filters[$name] = $options;
    }

    /**
     * Gets the filter definitions
     * @return array Array with the name of the filter as key and the
     * filter options as value
     */
    public function getFilters() {
        return $this->filters;
    }

    /**
     * Adds a validator definition
     * @param string $name Name of the validator
          * @param array $options Options for the validator
     * @return null
     */
    public function addValidator($name, array $options) {
        $this->validators[$name] = $options;
    }

    /**
     * Gets the validator definitions
     * @return array Array with the name of the validator as key and the
          * validator options as value
     */
    public function getValidators() {
        return $this->validators;
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

}
