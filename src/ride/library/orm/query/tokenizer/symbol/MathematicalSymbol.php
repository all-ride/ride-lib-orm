<?php

namespace ride\library\orm\query\tokenizer\symbol;

use ride\library\database\manipulation\expression\MathematicalExpression;
use ride\library\tokenizer\symbol\NestedSymbol;

/**
 * Nested condition symbol for the tokenizer
 */
class MathematicalSymbol extends NestedSymbol {

    /**
     * Constructs a new condition tokenizer
     * @return null
     */
    public function __construct() {
        parent::__construct(MathematicalExpression::NESTED_OPEN, MathematicalExpression::NESTED_CLOSE, null, false, false);
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
        $between = substr($toProcess, $positionOpen + $this->symbolOpenLength, $positionOpen + $positionClose - $lengthProcess);

        $process .= $between . $this->symbolClose;

        if (!$between || trim($before)) {
            return null;
        }

        return array($between);
    }

}