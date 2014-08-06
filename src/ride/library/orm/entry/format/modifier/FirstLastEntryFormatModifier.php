<?php

namespace ride\library\orm\entry\format\modifier;

use ride\library\reflection\ReflectionHelper;

/**
 * Modifier to get the first element of a multivalue field
 */
class FirstLastEntryFormatModifier implements EntryFormatModifier {

    /**
     * Instance of the reflection helper
     * @var ride\library\reflection\ReflectionHelper $reflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Constructs a new entry format modifier
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, $isFirst = true) {
        $this->reflectionHelper = $reflectionHelper;
        $this->isFirst = $isFirst;
    }

    /**
     * Gets the first element of the provided array value
     * @param mixed $value Array value
     * @param array $arguments Property names for the first element
     * @return mixed Null if no array value provided or when the properties not
     * found
     */
    public function modifyValue($value, array $arguments) {
        if (!is_array($value) || empty($value)) {
            return null;
        }

        if ($this->isFirst) {
            $value = array_shift($value);
        } else {
            $value = array_pop($value);
        }

        if ($value === null) {
            return $value;
        }

        if (!$arguments) {
            return (string) $value;
        }

        foreach ($arguments as $argument) {
            $value = $this->reflectionHelper->getProperty($value, $argument);

            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

}
