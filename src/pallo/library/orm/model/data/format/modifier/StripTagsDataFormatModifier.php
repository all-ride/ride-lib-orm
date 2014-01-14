<?php

namespace pallo\library\orm\model\data\format\modifier;

/**
 * Modifier to strip the HTML tags from a value
 */
class StripTagsDataFormatModifier implements DataFormatModifier {

    /**
     * Strips all the HTML tags from the provided value
     * @param string $value Value to strip from all the HTML tags
     * @param array $arguments Array with arguments for this modifier (not used)
     * @return string
     */
    public function modifyValue($value, array $arguments) {
        return strip_tags($value);
    }

}