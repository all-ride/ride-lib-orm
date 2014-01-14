<?php

namespace pallo\library\orm\query\parser;

use pallo\library\database\manipulation\condition\Condition;
use pallo\library\database\manipulation\condition\NestedCondition;
use pallo\library\database\manipulation\condition\SimpleCondition;
use pallo\library\database\manipulation\expression\CaseExpression;
use pallo\library\database\manipulation\expression\FieldExpression;
use pallo\library\database\manipulation\expression\FunctionExpression;
use pallo\library\database\manipulation\expression\MathematicalExpression;
use pallo\library\database\manipulation\expression\TableExpression;
use pallo\library\database\manipulation\expression\ScalarExpression;
use pallo\library\database\manipulation\expression\SqlExpression;
use pallo\library\orm\exception\OrmParseException;
use pallo\library\orm\query\tokenizer\ConditionTokenizer;
use pallo\library\orm\query\tokenizer\FieldTokenizer;
use pallo\library\orm\query\tokenizer\MathematicTokenizer;
use pallo\library\orm\query\ModelExpression;

/**
 * Parser for expression and conditions of a model query
 */
class ExpressionParser {

    /**
     * Regular expression to match model fields with optional alias. eg {field}, {field} AS f or {relationField.field}
     * @var string
     */
    const REGEX_FIELD = '/^[\\\\{]([a-zA-Z0-9_])*([.]([a-zA-Z0-9_])*)?[\\\\}]( AS ([a-zA-Z0-9_])*)?$/';

    /**
     * Regular expression to match functions. eg max({field}) AS m
     * @var string
     */
    const REGEX_FUNCTION = '/^(([a-zA-Z0-9_])*)\\(((.)*)\\)( AS (([a-zA-Z0-9_])*))?$/';

    /**
     * Regular expression to match scalar values. eg 15, 15.5 or "test string" AS s
     * @var string
     */
    const REGEX_SCALAR = '/^(([0-9](\\.[0-9])?)*|"((.)*)")( AS (([a-zA-Z0-9_])*))?$/';

    /**
     * Regular expression to match scalar values. eg 15, 15.5 or "test string" AS s
     * @var string
     */
    const REGEX_VARIABLE = '/^%([a-zA-Z0-9_])*%$/';

    /**
     * Regular expression to match case expressions with optional alias.
     * @var string
     */
    const REGEX_CASE = '/^(\\()?CASE((.)*)END(\\))?( AS ([a-zA-Z0-9_])*)?$/';

    /**
     * NULL value
     * @var string
     */
    const NULL = 'NULL';

    /**
     * Array with the comparison operators for simple conditions
     * @var array
     */
    private static $comparisonOperators;

    /**
     * Array with the mathematical operators for mathematical expressions
     * @var array
     */
    private static $mathematicalOperators;

    /**
     * Tokenizer for fields, needed for function arguments
     * @var pallo\library\orm\query\tokenizer\FieldTokenizer
     */
    private $fieldTokenizer;

    /**
     * Tokenizer for mathematical expressions
     * @var pallo\library\orm\query\tokenizer\MathematicTokenizer
     */
    private $mathematicTokenizer;

    /**
     * Tokenizer for conditions
     * @var pallo\library\orm\query\tokenizer\ConditionTokenizer
     */
    private $conditionTokenizer;

    /**
     * Array with variables to replace
     * @var array
     */
    private $variables;

    /**
     * Construct a new field parser
     * @param pallo\library\orm\query\tokenizer\FieldTokenizer $fieldTokenizer
     * @param pallo\library\orm\query\tokenizer\MathematicTokenizer $mathematicTokenizer
     * @param pallo\library\orm\query\tokenizer\ConditionTokenizer $conditionTokenizer
     * @return null
     */
    public function __construct(FieldTokenizer $fieldTokenizer = null, MathematicTokenizer $mathematicTokenizer = null, ConditionTokenizer $conditionTokenizer = null) {
        if ($fieldTokenizer === null) {
            $fieldTokenizer = new FieldTokenizer();
        }

        if ($mathematicTokenizer === null) {
            $mathematicTokenizer = new MathematicTokenizer();
        }

        if ($conditionTokenizer === null) {
            $conditionTokenizer = new ConditionTokenizer();
        }

        $this->fieldTokenizer = $fieldTokenizer;
        $this->mathematicTokenizer = $mathematicTokenizer;
        $this->conditionTokenizer = $conditionTokenizer;
    }

    /**
     * Sets variables to this field parser. Variables are checked in scalar expressions.
     * @param array $variables
     */
    public function setVariables(array $variables = null) {
        $this->variables = $variables;
    }

    /**
     * Parses the model condition into a database condition
     * @param pallo\library\orm\query\ModelExpression $condition Condition to parse
     * @return pallo\library\database\manipulation\condition\Condition
     */
    public function parseCondition(ModelExpression $condition) {
        $tokens = $this->conditionTokenizer->tokenize($condition->getExpression());

        $variables = $condition->getVariables();

        $this->setVariables($variables);

        if (count($tokens) == 1 && !is_array($tokens[0])) {
            $condition = $this->parseSimpleCondition($tokens[0]);
        } else {
            $condition = $this->parseNestedCondition($tokens);
        }

        $this->setVariables(null);

        return $condition;
    }

    /**
     * Parses condition tokens into a nested database condition
     * @param array $conditionTokens Array with the tokens from the condition tokenizer
     * @return pallo\library\database\manipulation\condition\NestedCondition
     */
    private function parseNestedCondition(array $conditionTokens) {
        $nestedCondition = new NestedCondition($operator);

        $operator = null;
        foreach ($conditionTokens as $token) {
            if ($token == Condition::OPERATOR_AND || $token == Condition::OPERATOR_OR) {
                $operator = $token;
                continue;
            }

            if (is_array($token)) {
                $nestedCondition->addCondition($this->parseNestedCondition($token), $operator);
            } else {
                $nestedCondition->addCondition($this->parseSimpleCondition($token), $operator);
            }

            $operator = null;
        }

        return $nestedCondition;
    }

    /**
     * Parses a simple condition token into a simple database condition
     * @param string $condition String of a simple condition
     * @return pallo\library\database\manipulation\condition\SimpleCondition
     * @throws pallo\library\orm\exception\OrmParseException when the provided condition is empty or invalid
     */
    private function parseSimpleCondition($condition) {
        if (!is_scalar($condition) && $condition == '') {
            throw new OrmParseException('Provided condition is empty');
        }

        $operators = $this->getComparisonOperators();
        foreach ($operators as $operator) {
            $operatorPosition = strpos($condition, $operator);
            if ($operatorPosition === false) {
                continue;
            }

            $expression1 = trim(substr($condition, 0, $operatorPosition));
            $expression1 = $this->parseExpression($expression1);

            $expression2 = trim(substr($condition, $operatorPosition + strlen($operator)));
            $expression2 = $this->parseExpression($expression2);

            return new SimpleCondition($expression1, $expression2, $operator);
        }

        throw new OrmParseException('Provided condition could not be parsed: ' . $condition);
    }

    /**
     * Parses the provided value into a database expression
     * @param string $value Value to parse
     * @return pallo\library\database\manipulation\expression\Expression
     */
    public function parseExpression($value) {
        $value = trim($value);

        $expression = $this->parseModelField($value);
        if ($expression !== false) {
            return $expression;
        }

        $expression = $this->parseVariable($value);
        if ($expression !== false) {
            return $expression;
        }

        $expression = $this->parseScalar($value);
        if ($expression !== false) {
            return $expression;
        }

        $expression = $this->parseMathematical($value);
        if ($expression !== false) {
            return $expression;
        }

        $expression = $this->parseFunction($value);
        if ($expression !== false) {
            return $expression;
        }

        $expression = $this->parseCase($value);
        if ($expression !== false) {
            return $expression;
        }

        return $this->parseSql($value);
    }

    /**
     * Parses the provided field name into a database field expression
     * @param string $fieldName String of the field expression
     * @return boolean|pallo\library\database\manipulation\expression\FieldExpression false if the
     *         provided field expression could not be parsed, the field expression object otherwise.
     */
    private function parseModelField($fieldName) {
        if (!preg_match(self::REGEX_FIELD, $fieldName)) {
            return false;
        }

        if (strpos($fieldName, ' AS ') !== false) {
            list($fieldName, $alias) = explode(' AS ', $fieldName);
        } else {
            $alias = null;
        }

        $fieldName = substr($fieldName, 1, strlen($fieldName) - 2);

        if (strpos($fieldName, '.') !== false) {
            list($tableName, $fieldName) = explode('.', $fieldName, 2);
            $table = new TableExpression($tableName);
        } else {
            $table = null;
        }

        return new FieldExpression($fieldName, $table, $alias);
    }

    /**
     * Parses the provided variable
     * @param string $variable String of a variable
     * @return boolean|pallo\library\database\manipulation\expression\ScalarExpression false if the
     *         provided field expression could not be parsed, the scalar expression object otherwise.
     */
    private function parseVariable($variable) {
        if (!$this->variables || !preg_match(self::REGEX_VARIABLE, $variable)) {
            return false;
        }

        $variable = $this->parseVariables($variable);

        return new ScalarExpression($variable);
    }

    /**
     * Parses the provided function call into a database function expression
     * @param string $function String of the function expression
     * @return boolean|pallo\library\database\manipulation\expression\FunctionExpression false if the
     *         provided function expression could not be parsed, the function expression object otherwise.
     */
    private function parseFunction($function) {
        if (!preg_match(self::REGEX_FUNCTION, $function, $matches)) {
            return false;
        }

        $name = trim($matches[1]);
        if (!$name) {
            return false;
        }

        $arguments = $matches[3];

        $alias = null;
        if (isset($matches[6])) {
            $alias = $matches[6];
        }

        $function = new FunctionExpression($name, $alias);

        if (strpos($arguments, 'DISTINCT ') === 0) {
            $function->setDistinct(true);
            $arguments = substr($arguments, 9);
        }

        $argumentTokens = $this->fieldTokenizer->tokenize($arguments);
        foreach ($argumentTokens as $argument) {
            $argument = $this->parseExpression($argument);
            $function->addArgument($argument);
        }

        return $function;
    }

    /**
     * Parses the provided value into a database scalar expression
     * @param string $scalar String of a scalar expression
     * @return boolean|pallo\library\database\manipulation\expression\ScalarExpression false if the
     *         provided scalar expression could not be parsed, the scalar expression object otherwise.
     */
    private function parseScalar($scalar) {
        if (!preg_match(self::REGEX_SCALAR, $scalar, $matches)) {
            return false;
        }

        $value = $matches[1];
        if (strlen($value) >= 2 && substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
            $value = substr($value, 1, -1);
        }

        $alias = null;
        if (array_key_exists(7, $matches)) {
            $alias = $matches[7];
        }

        $value = $this->parseVariables($value);

        return new ScalarExpression($value, $alias);
    }

    /**
     * Parses the provided case into a database case expression
     * @param string $case String of a case expression
     * @return boolean|pallo\library\database\manipulation\expression\CaseExpression false if the
     *         provided case expression could not be parsed, the case expression otherwise
     */
    private function parseCase($case) {
        if (!preg_match(self::REGEX_CASE, $case)) {
            return false;
        }

        if (strpos($case, ' AS ') !== false) {
            list($case, $alias) = explode(' AS ', $case);
        } else {
            $alias = null;
        }

        if (substr($case, 0, 1) == '(' && substr($case, -1) == ')') {
            $case = substr($case, 1, -1);
        }

        $case = substr($case, 5, -4);

        if (strpos($case, ' ELSE ') !== false) {
            list($conditions, $else) = explode(' ELSE ', $case);
        } else {
            $condition = $case;
            $else = null;
        }

        $conditions = explode('WHEN', $conditions);
        if (!$conditions) {
            return false;
        }

        $case = new CaseExpression();
        if ($alias) {
            $case->setAlias($alias);
        }
        if ($else) {
            $case->setDefaultExpression($this->parseExpression($else));
        }

        foreach ($conditions as $condition) {
            $condition = trim($condition);
            if (!$condition) {
                continue;
            }

            list($condition, $value) = explode(' THEN ', $condition);

            $condition = $this->parseCondition(new ModelExpression($condition));
            $value = $this->parseExpression($value);

            $case->addWhen($condition, $value);
        }

        return $case;
    }

    private function parseMathematical($value) {
        $mathematicalOperators = $this->getMathematicalOperators();

        $hasMathematicalOperator = false;
        foreach ($mathematicalOperators as $mathematicalOperator) {
            $mathematicalOperatorPosition = strpos($value, ' ' . $mathematicalOperator . ' ');
            if ($mathematicalOperatorPosition !== false) {
                $hasMathematicalOperator = true;

                break;
            }
        }

        if (!$hasMathematicalOperator) {
            return false;
        }

        if (strpos($value, ' AS ') !== false) {
            list($value, $alias) = explode(' AS ', $value);
        } else {
            $alias = null;
        }

        $tokens = $this->mathematicTokenizer->tokenize($value);

        if (count($tokens) == 1) {
            return false;
        }

        $mathematicalExpression = new MathematicalExpression($alias);

        $currentExpression = '';
        $mathematicalOperator = null;
        foreach ($tokens as $token) {
            if (in_array($token, $mathematicalOperators)) {
                $mathematicalOperator = $token;

                continue;
            }

            $expression = $this->parseExpression($token);

            $mathematicalExpression->addExpression($expression, $mathematicalOperator);
        }

        return $mathematicalExpression;
    }

    /**
     * Parses the provided sql into a database sql expression
     * @param string $sql String of a SQL expression
     * @return pallo\library\database\manipulation\expression\SqlExpresison
     */
    private function parseSql($sql) {
        $sql = $this->parseVariables($sql);

        return new SqlExpression($sql);
    }

    /**
     * Parse the variables in the provided value
     * @param string $value
     * @return string Provided value with the variables set on the placeholders.
     */
    private function parseVariables($value) {
        if (!$this->variables) {
            return $value;
        }

        foreach ($this->variables as $name => $variable) {
            $value = str_replace('%' . $name . '%', $variable, $value);
        }

        return $value;
    }

    /**
     * Gets an array with the comparison operators. The order is significant for the parsing of conditions.
     * @return array
     */
    private function getComparisonOperators() {
        if (self::$comparisonOperators) {
            return self::$comparisonOperators;
        }

        self::$comparisonOperators = array(
            Condition::OPERATOR_LESS_OR_EQUALS,
            Condition::OPERATOR_GREATER_OR_EQUALS,
            Condition::OPERATOR_NOT_EQUALS,
            Condition::OPERATOR_EQUALS,
            Condition::OPERATOR_LESS,
            Condition::OPERATOR_GREATER,
            Condition::OPERATOR_IS . ' ' . Condition::OPERATOR_NOT,
            Condition::OPERATOR_IS,
            Condition::OPERATOR_NOT . ' ' . Condition::OPERATOR_LIKE,
            Condition::OPERATOR_LIKE,
            Condition::OPERATOR_NOT . ' ' . Condition::OPERATOR_IN,
            Condition::OPERATOR_IN,
        );

        return self::$comparisonOperators;
    }

    /**
     * Gets an array with the comparison operators. The order is significant for the parsing of conditions.
     * @return array
     */
    private function getMathematicalOperators() {
        if (self::$mathematicalOperators) {
            return self::$mathematicalOperators;
        }

        self::$mathematicalOperators = array(
            MathematicalExpression::OPERATOR_ADDITION,
            MathematicalExpression::OPERATOR_SUBSTRACTION,
            MathematicalExpression::OPERATOR_MULTIPLICATION,
            MathematicalExpression::OPERATOR_DIVISION,
            MathematicalExpression::OPERATOR_MODULO,
            MathematicalExpression::OPERATOR_EXPONENTIATION,
        );

        return self::$mathematicalOperators;
    }

}