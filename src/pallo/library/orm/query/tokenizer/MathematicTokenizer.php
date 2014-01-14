<?php

namespace pallo\library\orm\query\tokenizer;

use pallo\library\database\manipulation\expression\MathematicalExpression;
use pallo\library\orm\exception\OrmException;
use pallo\library\orm\query\tokenizer\symbol\MathematicalSymbol;
use pallo\library\tokenizer\symbol\SimpleSymbol;
use pallo\library\tokenizer\Tokenizer;

/**
 * Tokenizer for a mathematical expression of a model query
 */
class MathematicTokenizer extends Tokenizer {

    /**
     * Constructs a new condition tokenizer
     * @return null
     */
    public function __construct() {
        $this->addSymbol(new SimpleSymbol(' ' . MathematicalExpression::OPERATOR_ADDITION . ' '));
        $this->addSymbol(new SimpleSymbol(' ' . MathematicalExpression::OPERATOR_SUBSTRACTION . ' '));
        $this->addSymbol(new SimpleSymbol(' ' . MathematicalExpression::OPERATOR_MULTIPLICATION . ' '));
        $this->addSymbol(new SimpleSymbol(' ' . MathematicalExpression::OPERATOR_DIVISION . ' '));
        $this->addSymbol(new SimpleSymbol(' ' . MathematicalExpression::OPERATOR_MODULO . ' '));
        $this->addSymbol(new SimpleSymbol(' ' . MathematicalExpression::OPERATOR_EXPONENTIATION . ' '));
        $this->addSymbol(new MathematicalSymbol());

        parent::setWillTrimTokens(true);
    }

    public function tokenize($string) {
        $tokens = parent::tokenize($string);

        if (count($tokens) !== 1) {
            return $tokens;
        }

        if (substr($string, 0, 1) == MathematicalExpression::NESTED_OPEN && substr($string, -1) == MathematicalExpression::NESTED_CLOSE) {
            $tokens = $this->tokenize(substr($string, 1, -1));
        }

        return $tokens;
    }

    /**
     * Sets whether this tokenizer will trim the resulting tokens. Tokens which are empty after trimming
     * will be removed. Nested tokens are untouched.
     * @param boolean $willTrimTokens True to trim the tokens, false otherwise
     * @return null
     * @throws pallo\library\orm\exception\OrmException when this method is called, this tokenizer always trims resulting tokens.
     */
    public function setWillTrimTokens($willTrimTokens) {
        throw new OrmException('Not allowed to set the trim tokens property for this tokenizer');
    }

}