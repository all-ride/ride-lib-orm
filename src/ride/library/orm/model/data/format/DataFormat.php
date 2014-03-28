<?php

namespace ride\library\orm\model\data\format;

use ride\library\orm\exception\OrmException;
use ride\library\reflection\ReflectionHelper;
use ride\library\tokenizer\symbol\NestedSymbol;
use ride\library\tokenizer\Tokenizer;

/**
 * Parser of a data format. A data format is used to parse a data object of a
 * model into a human readable string or to map a field to a named format. Can
 * be used to get a title, teaser, image or ... of a generic data object.
 */
class DataFormat {

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
     * The format string
     * @var string
     */
    private $format;

    /**
     * The variables in the format string
     * @var array
     */
    private $variables;

    /**
     * Constructs a new data format
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param string $format Format string
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the provided
     * format is empty or not a string
     */
    public function __construct(ReflectionHelper $reflectionHelper, $format, array $modifiers) {
        if (!is_string($format) || !$format) {
            throw new OrmException('Provided format is empty');
        }

        $this->format = $format;
        $this->variables = $this->getVariablesFromFormat($reflectionHelper, $format, $modifiers);
    }

    /**
     * Gets the used variables from the provided format
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param string $format Data format
     * @return array Array with the variable strings
     */
    protected function getVariablesFromFormat(ReflectionHelper $reflectionHelper, $format, array $modifiers) {
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
                $variables[$variableFormat] = new DataFormatVariable($reflectionHelper, $variableFormat, $modifiers);

                $isVariableOpen = false;
                $variableFormat = null;
            } else {
                $variableFormat .= $token;
            }
        }

        return $variables;
    }

    /**
     * Formats the data
     * @param mixed $data Model data object
     * @param string $format Format for the data
     * @return mixed
     */
    public function formatData($data) {
        $output = $this->format;

        foreach ($this->variables as $variableName => $variable) {
            $variableString = self::SYMBOL_OPEN . $variableName . self::SYMBOL_CLOSE;

            if ($output === $variableString) {
                return $variable->getValue($data);
            }

            $output = str_replace($variableString, $variable->getValue($data), $output);
        }

        return $output;
    }

}