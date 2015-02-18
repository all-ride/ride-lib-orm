<?php

namespace ride\library\orm\query\parser;

use ride\library\database\manipulation\condition\Condition;
use ride\library\database\manipulation\condition\NestedCondition;
use ride\library\database\manipulation\condition\SimpleCondition;
use ride\library\database\manipulation\expression\AliasExpression;
use ride\library\database\manipulation\expression\CaseExpression;
use ride\library\database\manipulation\expression\Expression;
use ride\library\database\manipulation\expression\FieldExpression;
use ride\library\database\manipulation\expression\FunctionExpression;
use ride\library\database\manipulation\expression\JoinExpression;
use ride\library\database\manipulation\expression\TableExpression;
use ride\library\database\manipulation\expression\OrderExpression;
use ride\library\database\manipulation\expression\ScalarExpression;
use ride\library\database\manipulation\expression\SqlExpression;
use ride\library\database\manipulation\statement\SelectStatement;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\exception\OrmException;
use ride\library\orm\exception\OrmParseException;
use ride\library\orm\meta\ModelMeta;
use ride\library\orm\model\LocalizedModel;
use ride\library\orm\query\ModelExpression;
use ride\library\orm\query\ModelJoin;
use ride\library\orm\query\ModelQuery;
use ride\library\orm\OrmManager;

/**
 * Object to parse model queries into database statements
 */
class QueryParser {

    /**
     * Alias for the table of the model
     * @var string
     */
    const ALIAS_SELF = 'self';

    /**
     * Alias for the table of the localized model
     * @var string
     */
    const ALIAS_SELF_LOCALIZED = 'selfLocalized';

    /**
     * Alias for the case expression to see if the data is localized
     * @var string
     */
    const ALIAS_IS_LOCALIZED = 'isLocalized';

    /**
     * Alias for the field with the number of results in a count statement
     * @var string
     */
    const ALIAS_COUNT = 'numResults';

    /**
     * Separator for the alias of a field to separate the table and the field
     * @var string
     */
    const ALIAS_SEPARATOR = '__';

    /**
     * Number of characters in the alias separator
     * @var integer
     */
    const ALIAS_SEPARATOR_LENGTH = 2;

    /**
     * Object to tokenize field strings
     * @var \ride\library\orm\query\tokenizer\FieldTokenizer
     */
    private $fieldTokenizer;

    /**
     * Object to parse strings into database expressions and conditions
     * @var ExpressionParser
     */
    private $expressionParser;

    /**
     * Instance of the model manager
     * @var \ride\library\orm\OrmManager
     */
    private $orm;

    /**
     * The meta definition of the model which we are querying
     * @var \ride\library\orm\model\ModelMeta
     */
    private $meta;

    /**
     * Depth of the relations to fetch
     * @var integer
     */
    private $recursiveDepth;

    /**
     * Flag to set whether localization of the data is required
     * @var boolean
     */
    private $localize;

    /**
     * The code of the locale to query with
     * @var string
     */
    private $locale;

    /**
     * Flag to see whether to include unlocalized data in the result
     * @var boolean
     */
    private $includeUnlocalized;

    /**
     * The statement we are building
     * @var \ride\library\database\manipulation\statement\SelectStatement
     */
    private $statement;

    /**
     * Array with the field expressions used in this statement, indexed on the alias
     * @var array
     */
    private $fields;

    /**
     * Array with the fields with a belongs to relation
     * @var array
     */
    private $recursiveBelongsToFields;

    /**
     * Array with the fields with a has relation
     * @var array
     */
    private $recursiveHasFields;

    /**
     * Array with the table expressions used in this statement, indexed on alias
     * @var array
     */
    private $tables;

    /**
     * Array with joins generated by the usage of relation fields
     * @var array
     */
    private $fieldJoins;

    /**
     * Array with joins generated by the usage of relation fields
     * @var array
     */
    private $conditionJoins;

    /**
     * Constructs a new model query parser
     * @param \ride\library\orm\OrmManager $orm
     * @return null
     */
    public function __construct(OrmManager $orm) {
        $this->fieldTokenizer = $orm->getFieldTokenizer();
        $this->expressionParser = new ExpressionParser($this->fieldTokenizer);
        $this->orm = $orm;
    }

    /**
     * Sets the model query to this parser and initializes the parser for this model query.
     * @param \ride\library\orm\query\ModelQuery $modelQuery
     * @return null
     */
    private function setModelQuery(ModelQuery $modelQuery) {
        $this->meta = $modelQuery->getModel()->getMeta();

        $this->recursiveDepth = $modelQuery->getRecursiveDepth();

        $this->localize = false;
        $this->locale = $modelQuery->getLocale();
        $this->includeUnlocalized = $modelQuery->willIncludeUnlocalized();
        $this->addIsLocalizedOrder = $modelQuery->willAddIsLocalizedOrder();

        $this->fields = array();
        $this->recursiveBelongsToFields = array();
        $this->recursiveHasFields = array();
        $this->tables = array();
        $this->fieldJoins = array();
        $this->conditionJoins = array();

        $this->addTable($this->meta->getName(), self::ALIAS_SELF);
        $this->addTable($this->meta->getName() . ModelMeta::SUFFIX_LOCALIZED, self::ALIAS_SELF_LOCALIZED);

        $this->statement = new SelectStatement();
        $this->statement->addTable($this->tables[self::ALIAS_SELF]);

        $joins = $modelQuery->getJoins();
        foreach ($joins as $join) {
            $table = $join->getTable();
            $this->addTable($table->getModelName(), $table->getAlias());
        }
    }

    /**
     * Gets the belongs to fields, of the last parsed query, which need a new query
     * @return array Array with the field name as key and a ModelField object as value
     */
    public function getRecursiveBelongsToFields() {
        return $this->recursiveBelongsToFields;
    }

    /**
     * Gets the has fields, of the last parsed query, which need a new query
     * @return array Array with the field name as key and a ModelField object as value
     */
    public function getRecursiveHasFields() {
        return $this->recursiveHasFields;
    }

    /**
     * Parses a model query into a database select statement to count the result
     * @param \ride\library\orm\query\ModelQuery $modelQuery
     * @return \ride\library\database\manipulation\statement\SelectStatement
     */
    public function parseQueryForCount(ModelQuery $modelQuery) {
        $this->setModelQuery($modelQuery);
        $this->statement->setLimit(1);

        if ($this->includeUnlocalized && $this->meta->isLocalized()) {
            $this->localize = true;
        }

        $joins = $this->parseJoins($modelQuery->getJoins());
        $conditions = $this->parseConditions($modelQuery->getConditions());
        $groupBy = $this->parseOrderBy($modelQuery->getGroupBy());

        $this->addJoins($joins);
        $this->addConditions($conditions, $modelQuery->getOperator());
//        $this->addGroupBy($groupBy);

        $countField = new FunctionExpression(FunctionExpression::FUNCTION_COUNT, self::ALIAS_COUNT);
        if ($groupBy) {
            $countField->setDistinct(true);
            foreach ($groupBy as $group) {
                $countField->addArgument($group->getExpression());
            }
        } else {
            if ($modelQuery->isDistinct()) {
                $countField->addArgument(new FieldExpression(ModelTable::PRIMARY_KEY, $this->tables[self::ALIAS_SELF]));
                $countField->setDistinct(true);
            } else {
                $countField->addArgument(new SqlExpression('*'));
            }
        }

        $this->statement->clearFields();
        $this->statement->clearOrderBy();
        $this->statement->addField($countField);

        return $this->statement;
    }

    /**
     * Parses a model query into a database select statement
     * @param \ride\library\orm\query\ModelQuery $modelQuery
     * @return \ride\library\database\manipulation\statement\SelectStatement
     */
    public function parseQuery(ModelQuery $modelQuery) {
        $this->setModelQuery($modelQuery);

        $this->statement->setDistinct($modelQuery->isDistinct());

        $limitCount = $modelQuery->getLimitCount();
        if ($limitCount) {
            $this->statement->setLimit($limitCount, $modelQuery->getLimitOffset());
        }

        $this->addFields($modelQuery->getFields());

        $joins = $this->parseJoins($modelQuery->getJoins());
        $conditions = $this->parseConditions($modelQuery->getConditions());
        $having = $this->parseConditions($modelQuery->getHaving());
        $groupBy = $this->parseOrderBy($modelQuery->getGroupBy());
        $orderBy = $this->parseOrderBy($modelQuery->getOrderBy());

        $this->addJoins($joins);
        $this->addConditions($conditions, $modelQuery->getOperator());
        $this->addHaving($having, $modelQuery->getOperator());
        $this->addGroupBy($groupBy);
        $this->addOrderBy($orderBy);

        return $this->statement;
    }

    /**
     * Adds the fields to the statement
     * @param array $fields Array with ModelExpression objects
     * @return null
     */
    private function addFields(array $fields) {
        foreach ($fields as $field) {
            $this->expressionParser->setVariables($field->getVariables());

            $tokens = $this->fieldTokenizer->tokenize($field->getExpression());

            foreach ($tokens as $token) {
                $expression = $this->expressionParser->parseExpression($token);

                if ($expression instanceof FieldExpression) {
                    $this->processAndAddFieldExpression($expression);

                    continue;
                }

                $expression = $this->processExpression($expression);

                $this->statement->addField($expression);

                if ($expression instanceof AliasExpression) {
                    $this->fields[$expression->getAlias()] = $expression;
                }
            }

            $this->expressionParser->setVariables(null);
        }
    }

    /**
     * Adds a field to the statement
     * @param TableExpression $table Table of the field
     * @param string $name Name of the field
     * @param string $aliasPrefix Prefix for the alias, the part before the alias separator
     * @return null
     */
    private function addField(TableExpression $table, $name, $aliasPrefix = null, $alias = null) {
        if (!$alias) {
            if ($aliasPrefix == null) {
                $aliasPrefix = self::ALIAS_SELF;
            }

            $alias = $aliasPrefix . self::ALIAS_SEPARATOR . $name;
        }

        $fieldExpression = new FieldExpression($name, $table, $alias);

        $this->statement->addField($fieldExpression);

        $this->fields[$alias] = $fieldExpression;
    }

    /**
     * Adds a belongs to field to the statement
     * @param \ride\library\orm\definition\field\BelongsToField $field
     * @return null;
     */
    private function addBelongsTo(BelongsToField $field) {
        $name = $field->getName();

        $relationModelName = $this->meta->getRelationModelName($name);
        $relationModel = $this->orm->getModel($relationModelName);

        $hasRelations = $relationModel->getMeta()->hasRelations();

        $useJoin = false;
        if ($this->recursiveDepth == 1 || ($this->recursiveDepth != 0 && !$hasRelations)) {
            $useJoin = true;
        } elseif ($this->recursiveDepth) {
            $this->recursiveBelongsToFields[$name] = $field;
        }

        if (!$useJoin) {
            if ($field->isLocalized()) {
                $this->addField($this->tables[self::ALIAS_SELF_LOCALIZED], $name);
                $this->localize = true;
            } else {
                $this->addField($this->tables[self::ALIAS_SELF], $name);
            }

            return;
        }

        $this->addFieldJoin($field);

        $relationTable = new TableExpression($relationModelName, $name);

        $relationLocalizedTable = null;
        if ($relationModel->getMeta()->isLocalized()) {
            $relationLocalizedTable = new TableExpression($relationModelName . ModelMeta::SUFFIX_LOCALIZED, $name . ModelMeta::SUFFIX_LOCALIZED);
        }

        $relationFields = $relationModel->getMeta()->getModelTable()->getFields();
        foreach ($relationFields as $relationField) {
            if ($relationField instanceof HasField) {
                continue;
            }

            if ($relationField->isLocalized()) {
                $this->addField($relationLocalizedTable, $relationField->getName(), $name);
            } else {
                $this->addField($relationTable, $relationField->getName(), $name);
            }
        }
    }

    /**
     * Adds all the joins to the statement
     * @param array $joins Array with ModelJoin objects
     * @return null
     */
    private function addJoins(array $joins) {
        if ($this->localize) {
            $this->addLocalizeJoin();
        }

        foreach ($this->fieldJoins as $field) {
            $fieldName = $field->getName();

            if ($field->isLocalized()) {
                $table = $this->tables[self::ALIAS_SELF_LOCALIZED];
            } else {
                $table = $this->tables[self::ALIAS_SELF];
            }

            $relationModelName = $this->meta->getRelationModelName($fieldName);
            $relationTable = new TableExpression($relationModelName, $fieldName);

            if ($field instanceof BelongsToField) {
                $this->addBelongsToJoin($table, $relationTable, $fieldName);

                continue;
            }

            $relationLinkModelName = $this->meta->getRelationLinkModelName($fieldName);
            if (!$relationLinkModelName) {
                $this->addHasOneJoin($table, $relationTable, $fieldName);

                continue;
            }

            $linkTable = new TableExpression($relationLinkModelName, lcfirst($relationLinkModelName));
            $this->addHasManyJoin($table, $relationTable, $linkTable, $fieldName);
        }

        foreach ($this->conditionJoins as $join) {
            $this->tables[self::ALIAS_SELF]->addJoin($join);
        }

        foreach ($joins as $join) {
            $this->tables[self::ALIAS_SELF]->addJoin($join);
        }
    }

    /**
     * Adds the join to localize the data of the model to the statement
     * @return null
     */
    private function addLocalizeJoin() {
        $joinCondition = $this->createLocalizeCondition($this->tables[self::ALIAS_SELF], $this->tables[self::ALIAS_SELF_LOCALIZED]);

        $localeField = new FieldExpression(LocalizedModel::FIELD_LOCALE, $this->tables[self::ALIAS_SELF_LOCALIZED], self::ALIAS_SELF . self::ALIAS_SEPARATOR . LocalizedModel::FIELD_LOCALE);
        $this->statement->addField($localeField);

        if ($this->includeUnlocalized) {
            $joinType = JoinExpression::TYPE_LEFT;

            $isLocalizedCondition = new SimpleCondition($localeField, new ScalarExpression(null), Condition::OPERATOR_IS);

            $isLocalizedCase = new CaseExpression();
            $isLocalizedCase->setAlias(self::ALIAS_IS_LOCALIZED);
            $isLocalizedCase->setDefaultExpression(new ScalarExpression(1));
            $isLocalizedCase->addWhen($isLocalizedCondition, new ScalarExpression(0));

            $this->statement->addField($isLocalizedCase);

            if ($this->addIsLocalizedOrder) {
                $order = new OrderExpression(new FieldExpression(self::ALIAS_IS_LOCALIZED), OrderExpression::DIRECTION_DESC);

                $this->statement->addOrderBy($order);
            }
        } else {
            $joinType = JoinExpression::TYPE_INNER;
        }

        $join = new JoinExpression($joinType, $this->tables[self::ALIAS_SELF_LOCALIZED], $joinCondition);
        $this->tables[self::ALIAS_SELF]->addJoin($join);
    }

    /**
     * Adds a join for a belongs to relation to the statement
     * @param \ride\library\database\manipulation\expression\TableExpression $table
     * @param \ride\library\database\manipulation\expression\TableExpression $relationTable
     * @param string $fieldName
     * @return null
     */
    private function addBelongsToJoin(TableExpression $table, TableExpression $relationTable, $fieldName) {
        $expressionForeignKey = new FieldExpression($fieldName, $table, self::ALIAS_SELF . self::ALIAS_SEPARATOR . $fieldName);
        $expressionPrimaryKey = new FieldExpression(ModelTable::PRIMARY_KEY, $relationTable, $fieldName . self::ALIAS_SEPARATOR . ModelTable::PRIMARY_KEY);
        $joinCondition = new SimpleCondition($expressionForeignKey, $expressionPrimaryKey, Condition::OPERATOR_EQUALS);

        $join = new JoinExpression(JoinExpression::TYPE_LEFT, $relationTable, $joinCondition);
        $this->tables[self::ALIAS_SELF]->addJoin($join);

        $relationModelName = $this->meta->getRelationModelName($fieldName);
        $relationModel = $this->orm->getModel($relationModelName);
        if (!$relationModel->getMeta()->isLocalized()) {
            return;
        }

        $relationLocalizedTable = new TableExpression($relationModelName . ModelMeta::SUFFIX_LOCALIZED, $fieldName . ModelMeta::SUFFIX_LOCALIZED);

        $joinCondition = $this->createLocalizeCondition($relationTable, $relationLocalizedTable);

        $join = new JoinExpression(JoinExpression::TYPE_LEFT, $relationLocalizedTable, $joinCondition);
        $this->tables[self::ALIAS_SELF]->addJoin($join);

        if ($this->recursiveDepth == 1) {
            $localeField = new FieldExpression(LocalizedModel::FIELD_LOCALE, $relationLocalizedTable, $fieldName . self::ALIAS_SEPARATOR . LocalizedModel::FIELD_LOCALE);

            $this->statement->addField($localeField);
        }
    }

    /**
     * Adds a join for a has one relation to the statement
     * @param \ride\library\database\manipulation\expression\TableExpression $table
     * @param \ride\library\database\manipulation\expression\TableExpression $relationTable
     * @param string $fieldName
     * @return null
     */
    private function addHasOneJoin(TableExpression $table, TableExpression $relationTable, $fieldName) {
        $foreignKey = $this->meta->getRelationForeignKey($fieldName);

        $expressionPrimaryKey = new FieldExpression(ModelTable::PRIMARY_KEY, $table, self::ALIAS_SELF . self::ALIAS_SEPARATOR . ModelTable::PRIMARY_KEY);
        $expressionForeignKey = new FieldExpression($foreignKey, $relationTable, $fieldName . self::ALIAS_SEPARATOR . $foreignKey);
        $joinCondition = new SimpleCondition($expressionPrimaryKey, $expressionForeignKey, Condition::OPERATOR_EQUALS);

        $join = new JoinExpression(JoinExpression::TYPE_LEFT, $relationTable, $joinCondition);

        $this->tables[self::ALIAS_SELF]->addJoin($join);
    }

    /**
     * Adds a join for a has many relation to the statement
     * @param \ride\library\database\manipulation\expression\TableExpression $table
     * @param \ride\library\database\manipulation\expression\TableExpression $relationTable
     * @param \ride\library\database\manipulation\expression\TableExpression $linkTable
     * @param string $fieldName
     * @return null
     */
    private function addHasManyJoin(TableExpression $table, TableExpression $relationTable, TableExpression $linkTable, $fieldName) {
        $this->statement->setDistinct(true);

        $foreignKeyToSelf = $this->meta->getRelationForeignKeyToSelf($fieldName);

        $expressionPrimaryKey = new FieldExpression(ModelTable::PRIMARY_KEY, $table, self::ALIAS_SELF . self::ALIAS_SEPARATOR . ModelTable::PRIMARY_KEY);
        $expressionForeignKey = new FieldExpression($foreignKeyToSelf, $linkTable, $linkTable->getAlias() . self::ALIAS_SEPARATOR . $foreignKeyToSelf);
        $joinCondition = new SimpleCondition($expressionPrimaryKey, $expressionForeignKey, Condition::OPERATOR_EQUALS);

        $join = new JoinExpression(JoinExpression::TYPE_LEFT, $linkTable, $joinCondition);

        $this->tables[self::ALIAS_SELF]->addJoin($join);

        $foreignKey = $this->meta->getRelationForeignKey($fieldName);
        $expressionPrimaryKey = new FieldExpression(ModelTable::PRIMARY_KEY, $relationTable, $fieldName . self::ALIAS_SEPARATOR . ModelTable::PRIMARY_KEY);
        $expressionForeignKey = new FieldExpression($foreignKey, $linkTable, $linkTable->getAlias() . self::ALIAS_SEPARATOR . $foreignKey);
        $joinCondition = new SimpleCondition($expressionPrimaryKey, $expressionForeignKey, Condition::OPERATOR_EQUALS);

        $join = new JoinExpression(JoinExpression::TYPE_LEFT, $relationTable, $joinCondition);

        $this->tables[self::ALIAS_SELF]->addJoin($join);
    }

    /**
     * Adds the condition expressions to the statement
     * @param array $condition Array with database condition objects
     * @param string $operator Operator for the nested conditions
     * @return null
     */
    private function addConditions(array $conditions, $operator) {
        $numConditions = count($conditions);
        if ($numConditions === 0) {
            return;
        }

        if ($numConditions === 1) {
            $this->statement->addCondition(array_shift($conditions));

            return;
        }

        $nestedCondition = new NestedCondition();
        foreach ($conditions as $condition) {
            $nestedCondition->addCondition($condition, $operator);
        }

        $this->statement->addCondition($nestedCondition);
    }


    /**
     * Adds the having condition expressions to the statement
     * @param array $condition Array with database condition objects
     * @param string $operator Operator for the nested conditions
     * @return null
     */
    private function addHaving(array $conditions, $operator) {
        $numConditions = count($conditions);
        if ($numConditions === 0) {
            return;
        }

        if ($numConditions === 1) {
            $this->statement->addHaving(array_shift($conditions));

            return;
        }

        $nestedCondition = new NestedCondition();
        foreach ($conditions as $condition) {
            $nestedCondition->addCondition($condition, $operator);
        }

        $this->statement->addHaving($nestedCondition);
    }

    /**
     * Adds the group by expressions to the statement
     * @param array $groupBy Array with database order expressions
     * @return null
     */
    private function addGroupBy(array $groupBy) {
        foreach ($groupBy as $group) {
            $this->statement->addGroupBy($group);
        }
    }

    /**
     * Adds the order by expressions to the statement
     * @param array $orderBy Array with database order expressions
     * @return null
     */
    private function addOrderBy(array $orderBy) {
        foreach ($orderBy as $order) {
            $this->statement->addOrderBy($order);
        }
    }

    /**
     * Processes the relations and localization of a model field and add the expression to the statement.
     * @param \ride\library\database\manipulation\expression\FieldExpression $fieldExpression
     * @return null
     */
    private function processAndAddFieldExpression(FieldExpression $fieldExpression) {
        $name = $fieldExpression->getName();
        $table = $fieldExpression->getTable();
        $alias = $fieldExpression->getAlias();

        if ($table !== null) {
            $this->addField($table, $name, null, $alias);

            return;
        }

        $field = $this->meta->getField($name);

        if ($field instanceof HasField) {
            $this->recursiveHasFields[$name] = $field;

            return;
        }

        if ($field instanceof BelongsToField) {
            $this->addBelongsTo($field);

            return;
        }

        if ($field->isLocalized()) {
            $this->addField($this->tables[self::ALIAS_SELF_LOCALIZED], $name, null, $alias);

            $this->localize = true;
        } else {
            $this->addField($this->tables[self::ALIAS_SELF], $name, null, $alias);
        }
    }

    /**
     * Processes the relations and localization of model fields used in the provided expression.
     * @param \ride\library\database\manipulation\expression\Expression $expression Expression to process
     * @param boolean $inCondition Flag to see whether the provided expression is used in a condition or not
     * @return \ride\library\database\manipulation\expression\Expression Processed expression
     */
    private function processExpression(Expression $expression, $inCondition = false) {
        if ($expression instanceof FieldExpression) {
            return $this->processFieldExpression($expression, $inCondition);
        }

        if ($expression instanceof FunctionExpression) {
            return $this->processFunctionExpression($expression, $inCondition);
        }

        return $expression;
    }

    /**
     * Processes the relations and localization of a field expression
     * @param \ride\library\database\manipulation\expression\FieldExpression $expression Field expression to process
     * @param boolean $inCondition Flag to see whether the provided expression is used in a condition or not
     * @return \ride\library\database\manipulation\expression\FieldExpression Processed expression
     */
    private function processFieldExpression(FieldExpression $expression, $inCondition = false) {
        $name = $expression->getName();
        $table = $expression->getTable();

        if (!$table && isset($this->fields[$name])) {
            return $this->fields[$name];
        }

        if (!$table || $table->getName() == $this->meta->getName() || $table->getName() == self::ALIAS_SELF) {
            return $this->processModelFieldExpression($name, $table, $inCondition);
        }

        return $this->processRelationFieldExpression($name, $table, $inCondition);
    }

    /**
     * Processes a field of the model
     * @param string $name Name of the field
     * @param TableExpression $table Table of the field
     * @param boolean $inCondition Flag to see whether the provided expression is used in a condition or not
     * @return \ride\library\database\manipulation\expression\FieldExpression
     */
    private function processModelFieldExpression($name, TableExpression $table = null, $inCondition = false) {
        $field = $this->meta->getField($name);

//        if (($this->recursiveDepth != 0 || $inCondition) && $field instanceof RelationField) {
//            $this->addFieldJoin($field);
//        }

        if ($field->isLocalized()) {
            $expression = new FieldExpression($name, $this->tables[self::ALIAS_SELF_LOCALIZED], self::ALIAS_SELF . self::ALIAS_SEPARATOR . $name);

            $this->localize = true;
        } else {
            $expression = new FieldExpression($name, $this->tables[self::ALIAS_SELF], self::ALIAS_SELF . self::ALIAS_SEPARATOR . $name);
        }

        return $expression;
    }

    /**
     * Processes a field of a relation model
     * @param string $name Name of the field
     * @param \ride\library\database\manipulation\expression\TableExpression $table Table expression for the field
     * @param boolean $inCondition Flag to see whether the provided expression is used in a condition or not
     * @return \ride\library\database\manipulation\expression\FieldExpression
     */
    private function processRelationFieldExpression($name, TableExpression $table, $inCondition) {
        $tableName = $table->getName();

        try {
            $field = $this->meta->getField($tableName);
        } catch (OrmException $e) {
            if (!isset($this->tables[$tableName])) {
                throw new OrmParseException('Table ' . $tableName . ' is not added to the query');
            }

            return new FieldExpression($name, $this->tables[$tableName], $tableName . self::ALIAS_SEPARATOR . $name);
        }

        $isRelationField = $field instanceof RelationField;
        if ($isRelationField) {
            $this->addFieldJoin($field);
        }

        $relationModelName = $this->meta->getRelationModelName($tableName);
        $relationModel = $this->orm->getModel($relationModelName);
        $relationField = $relationModel->getMeta()->getField($name);

        if ($relationField->isLocalized()) {
            $table = new TableExpression($relationModelName . ModelMeta::SUFFIX_LOCALIZED, $tableName . ModelMeta::SUFFIX_LOCALIZED);

            if ($inCondition && $isRelationField) {
                $relationTable = new TableExpression($relationModelName, $tableName);

                $joinCondition = $this->createLocalizeCondition($relationTable, $table);
                $join = new JoinExpression(JoinExpression::TYPE_LEFT, $table, $joinCondition);

                $this->addConditionJoin($join);
            }
        } else {
            $table = new TableExpression($relationModel->getName(), $tableName);
        }

        return new FieldExpression($name, $table, $tableName . self::ALIAS_SEPARATOR . $name);
    }

    /**
     * Processes the relations and the localization of the arguments used in a function expression
     * @param \ride\library\database\manipulation\expression\FunctionExpression $expression Function expression to process
     * @param boolean $inCondition Flag to see whether this expression is used in a condition or else where
     * @return \ride\library\database\manipulation\expression\FunctionExpression Processed function expression
     */
    private function processFunctionExpression(FunctionExpression $expression, $inCondition) {
        $function = new FunctionExpression($expression->getName(), $expression->getAlias());
        $function->setDistinct($expression->isDistinct());

        $arguments = $expression->getArguments();
        foreach ($arguments as $argument) {
            $argument = $this->processExpression($argument, $inCondition);
            $function->addArgument($argument);
        }

        return $function;
    }

    /**
     * Processes the relations and the localization in the expressions used by the provided condition
     * @param \ride\library\database\manipulation\condition\Condition $condition Condition to process
     * @return \ride\library\database\manipulation\condition\Condition Processed condition
     */
    private function processCondition(Condition $condition) {
        if ($condition instanceof NestedCondition) {
            return $this->processNestedCondition($condition);
        }

        if ($condition instanceof SimpleCondition) {
            return $this->processSimpleCondition($condition);
        }

        return $condition;
    }

    /**
     * Processes the relations and the localization in the expressions used by the provided nested condition
     * @param \ride\library\database\manipulation\condition\NestedCondition $condition Condition to process
     * @return \ride\library\database\manipulation\condition\NestedCondition $condition Processed condition
     */
    private function processNestedCondition(NestedCondition $condition) {
        $nestedCondition = new NestedCondition();

        $parts = $condition->getParts();
        foreach ($parts as $part) {
            $condition = $this->processCondition($part->getCondition());
            $nestedCondition->addCondition($condition, $part->getOperator());
        }

        return $nestedCondition;
    }

    /**
     * Processes the relations and the localization in the expressions used by the provided simple condition
     * @param \ride\library\database\manipulation\condition\SimpleCondition $condition Condition to process
     * @return \ride\library\database\manipulation\condition\SimpleCondition $condition Processed condition
     */
    private function processSimpleCondition(SimpleCondition $condition) {
        $expressionLeft = $condition->getLeftExpression();
        $expressionRight = $condition->getRightExpression();

        $expressionLeft = $this->processExpression($expressionLeft, true);
        $expressionRight = $this->processExpression($expressionRight, true);

        return new SimpleCondition($expressionLeft, $expressionRight, $condition->getOperator());
    }

    /**
     * Parse model conditions into database expressions
     * @param array $conditions Array with ModelExpression objects
     * @return array Array with database Condition objects
     */
    private function parseConditions(array $conditions) {
        $conditionExpressions = array();

        foreach ($conditions as $condition) {
            $conditionExpressions[] = $this->parseCondition($condition);
        }

        return $conditionExpressions;
    }

    /**
     * Parse and process a model condition into a database expression
     * @param \ride\library\orm\query\ModelExpression $condition
     */
    private function parseCondition(ModelExpression $condition) {
        $expression = $this->expressionParser->parseCondition($condition);

        return $this->processCondition($expression);
    }

    /**
     * Parse model joins into database join expressions
     * @param array $join Array with ModelJoin objects
     * @return array Array with JoinExpression objects
     */
    private function parseJoins(array $joins) {
        $joinExpressions = array();

        foreach ($joins as $join) {
            $joinExpressions[] = $this->parseJoin($join);
        }

        return $joinExpressions;
    }

    /**
     * Parses and processes a model join into a database join expression
     * @param \ride\library\orm\query\ModelJoin $join
     * @return \ride\library\database\manipulation\expression\JoinExpression
     */
    private function parseJoin(ModelJoin $join) {
        $condition = $join->getCondition();
        $condition = $this->parseCondition($condition);

        $table = $join->getTable();
        $table = $this->addTable($table->getModelName(), $table->getAlias());

        return new JoinExpression($join->getType(), $table, $condition);
    }

    /**
     * Parses and processes order strings into database order expressions
     * @param array $orderBy Array with order strings
     * @return array Array with OrderExpression objects
     */
    private function parseOrderBy(array $orderBy) {
        $parsedOrderBy = array();

        foreach ($orderBy as $index => $order) {
            $this->expressionParser->setVariables($order->getVariables());
            $tokens = $this->fieldTokenizer->tokenize($order->getExpression());

            foreach ($tokens as $token) {
                $parsedOrderBy[] = $this->parseOrder($token);
            }
        }

        $this->expressionParser->setVariables(null);

        return $parsedOrderBy;
    }

    /**
     * Parses and processes a order string into a database order expression
     * @param string $order String of an order expression
     * @return \ride\library\database\manipulation\expression\OrderExpression
     */
    private function parseOrder($order) {
        $positionDirection = strrpos($order, ' ');

        if ($positionDirection !== false) {
            $expression = substr($order, 0, $positionDirection);
            $direction = strtoupper(substr($order, $positionDirection + 1));

            if ($direction != OrderExpression::DIRECTION_ASC && $direction != OrderExpression::DIRECTION_DESC) {
                $expression = $order;
            }
        } else {
            $expression = $order;
            $direction = null;
        }

        $expression = $this->expressionParser->parseExpression($expression);

        $expression = $this->processExpression($expression);

        if ($expression instanceof FieldExpression && !isset($this->fields[$expression->getAlias()])) {
            $this->statement->addField($expression);
        }

        return new OrderExpression($expression, $direction);
    }

    /**
     * Adds a table which is used by the query
     * @param string $modelName Name of the model
     * @param string $alias Alias for the table
     * @return \ride\library\database\manipulation\expression\TableExpression
     */
    private function addTable($modelName, $alias) {
        return $this->tables[$alias] = new TableExpression($modelName, $alias);
    }

    /**
     * Adds a field to generate a join for when parsing the joins
     * @param \ride\library\orm\definition\field\RelationField $field
     * @return null
     */
    private function addFieldJoin(RelationField $field) {
        if ($field->isLocalized()) {
            $this->localize = true;
        }

        $this->fieldJoins[$field->getName()] = $field;
    }

    /**
     * Adds a join which came out of processing the conditions
     * @param \ride\library\database\manipulation\expression\JoinExpression $join
     * @return null
     */
    private function addConditionJoin(JoinExpression $join) {
        $this->conditionJoins[] = $join;
    }

    /**
     * Create a condition to localize a model
     * @param \ride\library\database\manipulation\expression\TableExpression $table Table expression of the table with the shared data
     * @param \ride\library\database\manipulation\expression\TableExpression $localizedTable Table expression of the table with the localized data
     * @return \ride\library\database\manipulation\condition\NestedCondition Condition to localize the provided table
     */
    private function createLocalizeCondition(TableExpression $table, TableExpression $localizedTable) {
        $expressionPrimaryKey = new FieldExpression(ModelTable::PRIMARY_KEY, $table, $table->getAlias() . self::ALIAS_SEPARATOR . ModelTable::PRIMARY_KEY);
        $expressionData = new FieldExpression(LocalizedModel::FIELD_ENTRY, $localizedTable, $localizedTable->getAlias() . self::ALIAS_SEPARATOR . LocalizedModel::FIELD_ENTRY);
        $expressionLocaleField = new FieldExpression(LocalizedModel::FIELD_LOCALE, $localizedTable, $localizedTable->getAlias() . self::ALIAS_SEPARATOR . LocalizedModel::FIELD_LOCALE);
        $expressionLocale = new ScalarExpression($this->locale);

        $dataCondition = new SimpleCondition($expressionPrimaryKey, $expressionData, Condition::OPERATOR_EQUALS);
        $localeCondition = new SimpleCondition($expressionLocaleField, $expressionLocale, Condition::OPERATOR_EQUALS);

        $localizeCondition = new NestedCondition();
        $localizeCondition->addCondition($dataCondition);
        $localizeCondition->addCondition($localeCondition);

        return $localizeCondition;
    }

}
