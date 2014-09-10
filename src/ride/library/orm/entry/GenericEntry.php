<?php

namespace ride\library\orm\entry;

/**
 * Generic data container
 */
class GenericEntry {

    /**
     * Id of the log data
     * @var integer|string
     */
    protected $id;

    /**
     * Gets a string representation of this data
     * @return string
     */
    public function __toString() {
        if ($this->id) {
            return 'Entry #' . $this->id;
        } else {
            return 'New Entry';
        }
    }

    /**
     * Sets a property
     * @param string $name Name of the property
     * @param mixed $value Value for the property
     * @return null
     */
    public function __set($name, $value) {
        $methodName = 'set' . ucfirst($name);
        if (method_exists($this, $methodName)) {
            $this->$methodName($value);
        } else {
            $this->$name = $value;
        }
    }

    /**
     * Gets a property
     * @param string $name Name of the property
     * @return mixed Value for the property
     */
    public function __get($name) {
        $methodName = 'get' . ucfirst($name);

        if (method_exists($this, $methodName)) {
            return $this->$methodName();
        } elseif (method_exists($this, $name)) {
            return $this->$name();
        } elseif (isset($this->$name)) {
            return $this->$name;
        }

        return null;
    }

    /**
     * Gets a property
     * @param string $name Name of the property
     * @return boolean
     */
    public function __isset($name) {
        return isset($this->$name);
    }

    /**
     * Unsets a property
     * @param string $name Name of the property
     * @return null
     */
    public function __unset($name) {
        if (isset($this->$name)) {
            unset($this->$name);
        }
    }

    /**
     * Sets the id of this entry
     * @param integer|string $id
     * @return null
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * Gets the id of this entry
     * @return integer|string
     */
    public function getId() {
        return $this->id;
    }

}
