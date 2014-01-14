<?php

namespace pallo\library\orm\model\data\format\modifier;

/**
 * Interface for modifiers of data format variables. To be used by piping the modifier after the value. eg {name|truncate}
 */
interface DataFormatModifier {

    /**
     * Modifies the value according to the implementation of this interface
     * @param string $value Value to modify
     * @param array $arguments Array with the arguments for this modifier
     * @return string
     */
    public function modifyValue($value, array $arguments);

}