<?php

namespace ride\library\orm\entry\format\modifier;

/**
 * Interface for modifiers of entry format variables.
 */
interface EntryFormatModifier {

    /**
     * Modifies the value according to the implementation of this interface
     * @param string $value Value to modify
     * @param array $arguments Array with the arguments for this modifier
     * @return string
     */
    public function modifyValue($value, array $arguments);

}
