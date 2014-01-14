<?php

namespace pallo\library\orm\query\tokenizer;

use pallo\library\orm\exception\OrmException;
use pallo\library\orm\query\tokenizer\symbol\NestedExpressionSymbol;
use pallo\library\tokenizer\symbol\SimpleSymbol;
use pallo\library\tokenizer\Tokenizer;

/**
 * Tokenizer for fields of a model query
 */
class FieldTokenizer extends Tokenizer {

    /**
     * Separator between the fields
     * @var string
     */
    const FIELD_SEPARATOR = ',';

    /**
     * Constructs a new field tokenizer
     * @return null
     */
    public function __construct() {
        $this->addSymbol(new SimpleSymbol(self::FIELD_SEPARATOR));
        $this->addSymbol(new NestedExpressionSymbol());

        parent::setWillTrimTokens(true);
    }

    /**
     * Tokenizes the provided string
     * @param string $string String to tokenize
     * @return array Array with the tokens of this string as value
     */
    public function tokenize($string) {
        $tokens = array();

        $parentTokens = parent::tokenize($string);

        $currentToken = '';
        foreach ($parentTokens as $token) {
            if ($token === self::FIELD_SEPARATOR) {
                $tokens[] = $currentToken;
                $currentToken = '';
                continue;
            }

            $currentToken .= ($currentToken ? ' ' : '') . $token;
        }

        if ($currentToken) {
            $tokens[] = $currentToken;
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