<?php

namespace pallo\library\orm\model\data\format\modifier;

use pallo\library\reflection\Boolean;
use pallo\library\String;

/**
 * Modifier to truncate a value
 */
class TruncateDataFormatModifier implements DataFormatModifier {

    /**
     * Truncates the provided value
     * @param string $value Value to truncate
     * @param array $arguments Array with arguments for the truncate function:
     *                         <ul>
     *                         <li>1 (integer): length to truncate (120)</li>
     *                         <li>2 (string) : etc string (...)</li>
     *                         <li>3 (boolean): flag to break words or not (false)</li>
     *                         </ul>
     * @return string
     */
    public function modifyValue($value, array $arguments) {
        $length = 120;
        $etc = '...';
        $breakWords = false;

        if (array_key_exists(0, $arguments)) {
            $length = $arguments[0];
        }

        if (array_key_exists(1, $arguments)) {
            $etc = $arguments[1];
        }

        if (array_key_exists(2, $arguments)) {
            $breakWords = Boolean::getBoolean($arguments[2]);
        }

        $value = new String($value);

        return $value->truncate($length, $etc, $breakWords);
    }

}