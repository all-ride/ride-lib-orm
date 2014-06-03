<?php

namespace ride\library\orm\entry\format\modifier;

/**
 * Modifier to capitalize a value
 */
class CapitalizeEntryFormatModifier implements EntryFormatModifier {

    /**
     * Regular expression to match the words of the value
     * @var string
     */
    const REGEX = '!\'?\b\w(\w|\')*\b!';

    /**
     * Capitalizes the provided value
     * @param string $value Value to capitalize
     * @param array $arguments Array with arguments for this modifier (not used)
     * @return string
     */
    public function modifyValue($value, array $arguments) {
        return preg_replace_callback(self::REGEX, array($this, 'capitalize'), $value);
    }

    /**
     * Capitalizes the first element of the provided array
     * @param array $strings Array with the matches of the regular expression
     * @return string
     */
    public function capitalize(array $strings) {
        return ucfirst($strings[0]);
    }

}
