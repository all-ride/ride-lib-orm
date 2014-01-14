<?php

namespace pallo\library\orm\model\data\format\modifier;

/**
 * Modifier to convert new lines into br HTML tags
 */
class DateDataFormatModifier implements DataFormatModifier {

    /**
     * Formats a date
     * @param string $value Value to convert all the new lines from
     * @param array $arguments Array with arguments for this modifier. The
     * format is set on key 0
     * @return string
     */
    public function modifyValue($value, array $arguments) {
        if (isset($arguments[0])) {
            $format = $arguments[0];
        } else {
            $format = 'Y-m-d H:i:s';
        }

        return date($format, $value);
    }

}