<?php

namespace ride\library\orm\model\data\format;

use ride\library\orm\exception\OrmException;
use ride\library\orm\model\data\format\modifier\DataFormatModifierFacade;
use ride\library\reflection\exception\ReflectionException;
use ride\library\reflection\ReflectionHelper;
use ride\library\tokenizer\symbol\NestedSymbol;
use ride\library\tokenizer\Tokenizer;

/**
 * Variable of a data format
 */
class DataFormatVariable {

    /**
     * Delimiter for a string value instead of a variable
     * @var string
     */
    const DELIMITER_STRING = '"';

    /**
     * Separator between the fields of a variable
     * @var string
     */
    const SEPARATOR_FIELD = '.';

    /**
     * Separator between the variable and the modifiers
     * @var string
     */
    const SEPARATOR_MODIFIER = '|';

    /**
     * Separator between the modifier and it's arguments
     * @var string
     */
    const SEPARATOR_ARGUMENT = ':';

    /**
     * Instance of the reflection helper
     * @var ride\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * The format string of the variable (includes the modifiers)
     * @var string
     */
    private $format;

    /**
     * The name of the variable
     * @var string
     */
    private $variable;

    /**
     * Flag to see if the variable is a variable or a string
     * @var boolean
     */
    private $isString;

    /**
     * The modifiers in the format string
     * @var array
     */
    private $modifiers;

    /**
     * The arguments for the modifiers
     * @var array
     */
    private $modifierArguments;

    /**
     * Constructs a new data format variable
     * @param ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param string $format Variable format string
     * @param array $modifiers Available modifiers
     * @return null
     * @throws ride\library\orm\exception\OrmException when the provided format
     * is empty or not a string
     */
    public function __construct(ReflectionHelper $reflectionHelper, $format, array $modifiers) {
        $this->reflectionHelper = $reflectionHelper;

        $this->setFormat($format, $modifiers);
    }

    /**
     * Gets a variable for the format
     * @param mixed $data Model data object
     * @param string $variableName Name of the variable
     * @return string
     */
    public function getValue($data) {
        if ($this->isString) {
            $value = $this->variable;
        } else {
            $tokens = explode(self::SEPARATOR_FIELD, $this->variable);

            $value = $data;
            foreach ($tokens as $token) {
                if (!is_object($value)) {
                    return null;
                }

                try {
                    $value = $this->reflectionHelper->getProperty($value, $token);
                } catch (ReflectionException $exception) {
                    return null;
                }
            }
        }

        if (!$this->modifiers) {
            return $value;
        }

        foreach ($this->modifierArguments as $name => $arguments) {
            $value = $this->modifiers[$name]->modifyValue($value, $arguments);
        }

        return $value;
    }

    /**
     * Gets the format string of this variable
     * @return string
     */
    public function getFormat() {
        return $this->format;
    }

    /**
     * Sets the format for this data format variable. This will parse the
     * variable for quicker data formatting.
     * @param string $format The variable format string
     * @param array $modifiers Available modifiers
     * @return null
     * @throws ride\library\orm\exception\OrmException when the provided format
     * is empty or not a string
     */
    private function setFormat($format, array $modifiers) {
        if (!is_string($format) || !$format) {
            throw new OrmException('Provided format is empty or not a string');
        }

        $tokens = explode(self::SEPARATOR_MODIFIER, $format);

        $this->format = $format;
        $this->variable = array_shift($tokens);
        $this->modifierArguments = $this->getModifierArgumentsFromTokens($tokens);

        $this->modifiers = array();
        foreach ($this->modifierArguments as $name => $arguments) {
            if (!isset($modifiers[$name])) {
                throw new OrmException('No data format modifier available for ' . $name);
            }

            $this->modifiers[$name] = $modifiers[$name];
        }

        $this->isString = false;
        if (substr($this->variable, 0, 1) == self::DELIMITER_STRING && substr($this->variable, -1) == self::DELIMITER_STRING) {
            $this->isString = true;
            $this->variable = substr($this->variable, 1, -1);
        }
    }

    /**
     * Gets the used modifiers from the provided format
     * @param array $tokens Tokens of the modifiers
     * @return array Array with the modifiers
     */
    private function getModifierArgumentsFromTokens(array $tokens) {
        if (!$tokens) {
            return array();
        }

        $modifiers = array();
        foreach ($tokens as $token) {
            $arguments = explode(self::SEPARATOR_ARGUMENT, $token);
            $name = array_shift($arguments);
            $modifiers[$name] = $arguments;
        }

        return $modifiers;
    }

}