<?php

namespace ride\library\orm\entry\format\modifier;

/**
 * Modifier to convert new lines into br HTML tags
 */
class Nl2brEntryFormatModifier implements EntryFormatModifier {

    /**
     * Converts all new lines into br HTML tags
     * @param string $value Value to convert all the new lines from
     * @param array $arguments Array with arguments for this modifier (not used)
     * @return string
     */
    public function modifyValue($value, array $arguments) {
        return nl2br($value);
    }

}
