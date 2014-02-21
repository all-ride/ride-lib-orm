<?php

namespace ride\library\orm\query\tokenizer\symbol;

use ride\library\database\manipulation\condition\Condition;
use ride\library\tokenizer\symbol\NestedSymbol;
use ride\library\tokenizer\Tokenizer;

/**
 * Nested condition symbol for the tokenizer
 */
class ConditionSymbol extends NestedSymbol {

    /**
     * Symbol to open nested conditions
     * @var string
     */
    const CONDITION_OPEN = '(';

    /**
     * Symbol to close nested conditions
     * @var string
     */
    const CONDITION_CLOSE = ')';

    /**
     * Constructs a new condition tokenizer
     * @return null
     */
    public function __construct(Tokenizer $tokenizer) {
        parent::__construct(self::CONDITION_OPEN, self::CONDITION_CLOSE, $tokenizer);
    }

    /**
     * Checks for this symbol in the string which is being tokenized.
     * @param string $inProcess Current part of the string which is being tokenized.
     * @param string $toProcess Remaining part of the string which has not yet been tokenized
     * @return null|array Null when the symbol was not found, an array with the processed tokens if the symbol was found.
     */
    public function tokenize(&$process, $toProcess) {
        $processLength = strlen($process);
        if ($processLength < $this->symbolOpenLength || substr($process, $this->symbolOpenOffset) != $this->symbolOpen) {
            return null;
        }

        $positionOpen = $processLength - $this->symbolOpenLength;
        $positionClose = $this->getClosePosition($toProcess, $positionOpen);
        $lengthProcess = strlen($process) + $positionOpen;

        $before = substr($process, 0, $positionOpen);
        if (trim($before) || !$this->isNestedCondition($toProcess, $positionClose)) {
            return null;
        }

        $between = substr($toProcess, $positionOpen + $this->symbolOpenLength, $positionOpen + $positionClose - $lengthProcess);
        if (!$between) {
            return null;
        }

        $betweenTokens = $this->tokenizer->tokenize($between);

        $process .= $between . $this->symbolClose;

        return array($betweenTokens);
    }

    /**
     * Checks what comes after the close symbol. If it's empty or a condition operator, the process string will be seen as a condition
     * @param string $toProcess
     * @param integer $positionClose
     * @return boolean True if the process string is to be seen as a condition, false otherwise
     */
    private function isNestedCondition($toProcess, $positionClose) {
        $positionAfter = $positionClose + 1;

        $toProcess = trim(substr($toProcess, $positionAfter));

        if (!$toProcess) {
            return true;
        }

        if (strlen($toProcess) > 2 && strtoupper(substr($toProcess, 0, 2)) == Condition::OPERATOR_OR) {
            return true;
        }
        if (strlen($toProcess) > 3 && strtoupper(substr($toProcess, 0, 3)) == Condition::OPERATOR_AND) {
            return true;
        }

        return false;
    }

}