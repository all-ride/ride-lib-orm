<?php

namespace ride\library\orm\entry\format\modifier;

/**
 * Modifier to escape the HTML tags from a value
 */
class EscapeEntryFormatModifier implements EntryFormatModifier {

    /**
     * Strips all the HTML tags from the provided value
     * @param string $value Value to strip from all the HTML tags
     * @param array $arguments Array with arguments for this modifier (not used)
     * @return string
     */
    public function modifyValue($value, array $arguments) {
        return htmlspecialchars($value, ENT_QUOTES);
    }

}
