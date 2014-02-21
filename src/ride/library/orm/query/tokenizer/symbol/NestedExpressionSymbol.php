<?php

namespace ride\library\orm\query\tokenizer\symbol;

use ride\library\tokenizer\symbol\NestedSymbol;

/**
 * Nested symbol to skip tokens between ( and )
 */
class NestedExpressionSymbol extends NestedSymbol {

    /**
     * Symbol to open a nested token
     * @var string
     */
    const NESTED_OPEN = '(';

    /**
     * Symbol to close a nested token
     * @var string
     */
    const NESTED_CLOSE = ')';

    /**
     * Constructs a new nested symbol
     * @return null
     */
    public function __construct() {
        parent::__construct(self::NESTED_OPEN, self::NESTED_CLOSE, null, false);
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

        return null;
    }

}