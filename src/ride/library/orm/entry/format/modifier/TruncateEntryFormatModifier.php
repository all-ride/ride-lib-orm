<?php

namespace ride\library\orm\entry\format\modifier;

use ride\library\reflection\Boolean;
use ride\library\StringHelper;

/**
 * Modifier to truncate a value
 */
class TruncateEntryFormatModifier implements EntryFormatModifier {

    /**
     * Truncates the provided value
     * @param string $value Value to truncate
     * @param array $arguments Array with arguments for the truncate function:
     * <ul>
     * <li>1 (integer): length to truncate (120)</li>
     * <li>2 (string) : etc string (...)</li>
     * <li>3 (boolean): flag to break words or not (false)</li>
     * </ul>
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

        return StringHelper::truncate($value, $length, $etc, $breakWords);
    }

}
