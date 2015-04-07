<?php

namespace ride\library\orm\entry;

/**
 * Generic data container
 */
class GenericEntry extends AbstractEntry {

    /**
     * Gets a string representation of this data
     * @return string
     */
    public function __toString() {
        return 'Entry #' . $this->id;
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

}
