<?php

namespace ride\library\orm\entry\format;

use ride\library\reflection\ReflectionHelper;

/**
 * Parser of an entry format. It is used to parse an entry of a model into a
 * human readable version or to map a field to a named format. Can be used
 * to get a title, teaser, image or ... of a generic entry.
 */
interface EntryFormat {

    /**
     * Sets the format
     * @param string $format Format string
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the provided
     * format is empty or not a string
     */
    public function setFormat($format);

    /**
     * Formats an entry
     * @param mixed $entry Entry instance
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @return mixed
     */
    public function formatEntry($entry, ReflectionHelper $reflectionHelper);

}
