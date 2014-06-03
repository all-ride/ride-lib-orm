<?php

namespace ride\library\orm\entry\format;

use ride\library\reflection\ReflectionHelper;

/**
 * Generic formatter of model entries
 */
class GenericEntryFormatter implements EntryFormatter {

    /**
     * Instance of the reflection helper
     * @var \ride\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Entry format modifiers for variable parsing
     * @var array
     */
    private $modifiers;

    /**
     * Initialized formats
     * @var array
     */
    private $formats;

    /**
     * Construct a new entry formatter
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param array $modifiers Available modifiers
     * @return null;
     */
    public function __construct(ReflectionHelper $reflectionHelper, array $modifiers) {
        $this->reflectionHelper = $reflectionHelper;
        $this->modifiers = $modifiers;

        $this->formats = array();
    }

    /**
     * Formats the entry
     * @param mixed $entry Entry instance
     * @param string $format Format string
     * @return mixed A formatted version of the entry
     */
    public function formatEntry($entry, $format) {
        if (!isset($this->formats[$format])) {
            $this->formats[$format] = $this->createEntryFormat($format);
        }

        return $this->formats[$format]->formatEntry($entry, $this->reflectionHelper);
    }

    /**
     * Creates a entry format
     * @param string $format Format string
     * @return EntryFormat
     */
    protected function createEntryFormat($format) {
        $entryFormat = new GenericEntryFormat($this->modifiers);
        $entryFormat->setFormat($format);

        return $entryFormat;
    }

}
