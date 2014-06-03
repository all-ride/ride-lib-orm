<?php

namespace ride\library\orm\entry\format;

use ride\library\orm\exception\OrmException;
use ride\library\reflection\ReflectionHelper;
use ride\library\tokenizer\symbol\NestedSymbol;
use ride\library\tokenizer\Tokenizer;

/**
 * Parser of a data format. A data format is used to parse a data object of a
 * model into a human readable string or to map a field to a named format. Can
 * be used to get a title, teaser, image or ... of a generic data object.
 */
class GenericEntryFormat implements EntryFormat {

    /**
     * Symbol to open a variable
     * @var string
     */
    const SYMBOL_OPEN = '{';

    /**
     * Symbol to close a variable
     * @var string
     */
    const SYMBOL_CLOSE = '}';

    /**
     * Format string
     * @var string
     */
    private $format;

    /**
     * Variables in the format string
     * @var array
     */
    private $variables;

    /**
     * Constructs a new entry format
     * @param array $modifiers
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the provided
     * format is empty or not a string
     * @see \ride\library\orm\entry\format\modifier\EntryFormatModifier
     */
    public function __construct(array $modifiers) {
        $this->modifiers = $modifiers;
        $this->variables = array();
    }

    /**
     * Sets the format
     * @param string $format Format string
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the provided
     * format is empty or not a string
     */
    public function setFormat($format) {
        if (!is_string($format) || !$format) {
            throw new OrmException('Provided format is empty');
        }

        $this->format = $format;
        $this->variables = $this->getVariablesFromFormat($format);
    }

    /**
     * Gets the used variables from the provided format
     * @param string $format Data format
     * @return array Array with the variable strings
     */
    protected function getVariablesFromFormat($format) {
        $symbol = new NestedSymbol(self::SYMBOL_OPEN, self::SYMBOL_CLOSE, null, true);
        $tokenizer = new Tokenizer();
        $tokenizer->addSymbol($symbol);

        $tokens = $tokenizer->tokenize($format);

        $variables = array();

        $isVariableOpen = false;
        $variableFormat = null;
        foreach ($tokens as $token) {
            if ($token == self::SYMBOL_OPEN) {
                $isVariableOpen = true;
                continue;
            }

            if (!$isVariableOpen) {
                continue;
            }

            if ($token == self::SYMBOL_CLOSE) {
                $variables[$variableFormat] = new GenericEntryFormatVariable($variableFormat, $this->modifiers);

                $isVariableOpen = false;
                $variableFormat = null;
            } else {
                $variableFormat .= $token;
            }
        }

        return $variables;
    }

    /**
     * Formats an entry
     * @param mixed $entry Entry instance
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @return mixed
     */
    public function formatEntry($entry, ReflectionHelper $reflectionHelper) {
        $result = $this->format;

        foreach ($this->variables as $variableName => $variable) {
            $variableString = self::SYMBOL_OPEN . $variableName . self::SYMBOL_CLOSE;

            if ($result === $variableString) {
                return $variable->getValue($entry, $reflectionHelper);
            }

            $result = str_replace($variableString, $variable->getValue($entry, $reflectionHelper), $result);
        }

        return $result;
    }

}
