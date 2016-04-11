<?php

namespace ride\library\orm\entry\format\modifier;

use ride\library\decorator\TimeDecorator;

/**
 * Modifier to convert new lines into br HTML tags
 */
class TimeEntryFormatModifier implements EntryFormatModifier {

    private $timeDecorator;

    public function __construct(TimeDecorator $timeDecorator) {
        $this->timeDecorator = $timeDecorator;
    }

    /**
     * Formats a date
     * @param string $value Value to convert all the new lines from
     * @param array $arguments Array with arguments for this modifier. The
     * format is set on key 0
     * @return string
     */
    public function modifyValue($value, array $arguments = null) {

        return $this->timeDecorator->decorate($value);
        
    }

}
