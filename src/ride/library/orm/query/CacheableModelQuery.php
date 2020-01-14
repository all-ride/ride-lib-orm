<?php

namespace ride\library\orm\query;

use ride\library\database\manipulation\statement\SelectStatement;
use ride\library\orm\model\Model;
use ride\library\orm\query\parser\QueryParser;

/**
 * Cacheable ORM model query
 */
class CacheableModelQuery extends ModelQuery {

    /**
     * Name of the meta variable to hold the used models
     * @var string
     */
    const CACHE_META_MODELS = 'models';

    /**
     * Name of the meta variable to hold the has fields
     * @var string
     */
    const CACHE_META_HAS = 'has';

    /**
     * Name of the meta variable to hold the belongsTo fields
     * @var string
     */
    const CACHE_META_BELONGS = 'belongs';

    /**
     * Array with all the variables of this query
     * @var array
     */
    private $variables;

    /**
     * Flag to see if the result should be cached
     * @var boolean
     */
    private $ttl;

    /**
     * Constructs a new model query
     * @param \ride\library\orm\model\Model $model Instance of the model for
     * this query
     * @param array $locales Array with the available locales, locale code as
     * key
     * @param string $locale The locale for the query
     * @return null
     */
    public function __construct(Model $model, array $locales, $locale) {
        parent::__construct($model, $locales, $locale);

        $this->variables = array();
        $this->ttl = null;
    }

    /**
     * Gets a string representation of this query
     * @return string
     */
    public function __toString() {
        return $this->getQueryId();
    }

    /**
     * Sets the cache TTL of the result, by setting this you enable the result
     * cache on this query
     * @param integer|null $ttl Time to live in seconds, 0 for eternal and null
     * to disable the result cache
     * @return null
     */
    public function setCacheTtl($ttl) {
        if ($ttl !== null && (!is_integer($ttl) || $ttl < 0)) {
            throw new OrmException('Could not set the TTL of this query: invalid ttl provided');
        }

        $this->ttl = $ttl;
    }

    /**
     * Gets the cache TTL of the result
     * @return integer|null Time to live in seconds, 0 for eternal and null to
     * disable the result cache
     */
    public function getCacheTtl() {
        return $this->ttl;
    }

    /**
     * Gets the SQL of the count query
     * @return string The SQL of the count query
     */
    public function getCountSql() {
        $sql = parent::getCountSql();

        $connection = $this->orm->getConnection();

        return $this->parseVariablesIntoSql($sql, $connection);
    }

    /**
     * Gets the SQL of the result query
     * @return string The SQL of the result query
     */
    public function getQuerySql() {
        $sql = parent::getQuerySql();

        $connection = $this->orm->getConnection();

        return $this->parseVariablesIntoSql($sql, $connection);
    }

    /**
     * Counts the results for this query
     * @return integer number of rows in this query
     */
    public function count() {
        $queryCache = $this->orm->getQueryCache();
        $resultCache = $this->orm->getResultCache();
        if (!$queryCache && !$resultCache) {
            return parent::count();
        }

        $queryId = $this->getQueryId('##count##');

        if ($this->ttl !== null && $resultCache) {
            $resultId = $this->getResultId($queryId);

            $cachedResult = $resultCache->get($resultId);
            if ($cachedResult->isValid()) {
                return $cachedResult->getValue();
            }
        }

        $connection = $this->orm->getConnection();

        $cachedQuery = $queryCache->get($queryId);
        if (!$cachedQuery->isValid()) {
            $statement = $this->queryParser->parseQueryForCount($this);

            $statementParser = $connection->getStatementParser();

            $sql = $statementParser->parseStatement($statement);
            $usedModels = $this->getUsedModels($statement);

            $cachedQuery->setValue($sql);
            $cachedQuery->setMeta(self::CACHE_META_MODELS, $usedModels);

            $queryCache->set($cachedQuery);
        } else {
            $sql = $cachedQuery->getValue();
            $usedModels = $cachedQuery->getMeta(self::CACHE_META_MODELS, array());
        }

        $sql = $this->parseVariablesIntoSql($sql, $connection);

        $result = $connection->execute($sql);

        if ($result->getRowCount()) {
            $row = $result->getFirst();
            $result = $row[QueryParser::ALIAS_COUNT];
        } else {
            $result = 0;
        }

        if ($this->ttl !== null && $resultCache) {
            $cachedResult->setValue($result);
            $cachedResult->setTtl($this->ttl);

            $resultCache->set($cachedResult);
        }

        return $result;
    }

    /**
     * Executes this query and returns the result
     * @param boolean $indexField Name of the field to use as key in the
     * resulting array, default is id
     * @return array Array with data objects
     */
    public function query($parse = null) {
        $queryCache = $this->orm->getQueryCache();
        $resultCache = $this->orm->getResultCache();
        if (!$queryCache && !$resultCache) {
            return parent::query($parse);
        }

        $queryId = $this->getQueryId($parse);

        $result = null;

        if ($this->ttl !== null && $resultCache) {
            $resultId = $this->getResultId($queryId);

            $cachedResult = $resultCache->get($resultId);
            if ($cachedResult->isValid()) {
                $result = $cachedResult->getValue();
                $belongsTo = $cachedResult->getMeta(self::CACHE_META_BELONGS, array());
                $has = $cachedResult->getMeta(self::CACHE_META_HAS, array());
            }
        }

        if (!$result) {
            $connection = $this->orm->getConnection();

            $cachedQuery = $queryCache->get($queryId);
            if (!$cachedQuery->isValid()) {
                $statement = $this->queryParser->parseQuery($this);

                $statementParser = $connection->getStatementParser();

                $sql = $statementParser->parseStatement($statement);
                $belongsTo = $this->queryParser->getRecursiveBelongsToFields();
                $has = $this->queryParser->getRecursiveHasFields();
                $usedModels = $this->getUsedModels($statement);

                $cachedQuery->setValue($sql);
                $cachedQuery->setMeta('models', $usedModels);
                $cachedQuery->setMeta('belongs', $belongsTo);
                $cachedQuery->setMeta('has', $has);

                $queryCache->set($cachedQuery);
            } else {
                $sql = $cachedQuery->getValue();
                $belongsTo = $cachedQuery->getMeta(self::CACHE_META_BELONGS, array());
                $has = $cachedQuery->getMeta(self::CACHE_META_HAS, array());
                $usedModels = $cachedQuery->getMeta(self::CACHE_META_MODELS, array());
            }

            $sql = $this->parseVariablesIntoSql($sql, $connection);

            $result = $connection->execute($sql);

            if ($this->ttl !== null && $resultCache) {
                $cachedResult->setTtl($this->ttl);
                $cachedResult->setValue($result);
                $cachedResult->setMeta(self::CACHE_META_BELONGS, $belongsTo);
                $cachedResult->setMeta(self::CACHE_META_HAS, $has);
                $cachedResult->setMeta(self::CACHE_META_MODELS, $usedModels);

                $resultCache->set($cachedResult);
            }
        }

        if ($parse !== false) {
            $result = $this->parseResult($result, $belongsTo, $has, $parse);
        }

        return $result;
    }

    /**
     * Gets a unique identifier of this query
     * @param string $suffix Suffix for the query id before hashing it
     * @return string
     */
    public function getQueryId($suffix = null) {
        $modelName = $this->model->getName();

        $queryId =
            $modelName . '-' .
            $this->operator . '-' .
            $this->distinct . '-' .
            $this->recursiveDepth . '-' .
            $this->locale . '-' .
            $this->includeUnlocalized . '-' .
            $this->fetchUnlocalized . '-' .
            $this->addIsLocalizedOrder . ';';

        if ($this->fields) {
            $queryId .= 'F:';
            foreach ($this->fields as $field) {
                $queryId .= $field->getExpression() . ';';
            }
        }

        $queryId .= 'J:';
        foreach ($this->joins as $join) {
            $table = $join->getTable();

            $queryId .= $join->getType() . ' ';
            $queryId .= $table->getModelName() . '-' . $table->getAlias() . ' ON ';
            $queryId .= $join->getCondition()->getExpression() . ';';
        }

        $queryId .= 'C:';
        foreach ($this->conditions as $condition) {
            $queryId .= $condition->getExpression() . ';';
        }

        $queryId .= 'G:';
        foreach ($this->groupBy as $groupBy) {
            $queryId .= $groupBy->getExpression() . ';';
        }

        $queryId .= 'H:';
        foreach ($this->having as $condition) {
            $queryId .= $condition->getExpression() . ';';
        }

        $queryId .= 'O:';
        foreach ($this->orderBy as $orderBy) {
            $queryId .= $orderBy->getExpression() . ';';
        }

        if ($this->limitCount !== null) {
            $queryId .= 'Lc' . $this->limitCount;
        }
        if ($this->limitOffset !== null) {
            $queryId .= 'Lo' . $this->limitOffset;
        }

        $queryId .= $suffix;

        // $queryId .= 'L:' . $this->limitCount . '-' . $this->limitOffset . ';' . $suffix;

        $queryId = $modelName . '-' . md5($queryId);

        return $queryId;
    }

    /**
     * Gets a unique identifier of this query with these variables
     * @param string $queryId Unique identifier of the query
     * @return string
     */
    public function getResultId($queryId) {
        $variableString = '';

        foreach ($this->variables as $key => $value) {
            $variableString .= $key . ': ' . $value .  ';';
        }

        if ($this->limitCount !== null) {
            $variableString .= 'Lc:' . $this->limitCount;
        }
        if ($this->limitOffset !== null) {
            $variableString .= 'Lo:' . $this->limitOffset;
        }

        return $queryId . '-' . md5($variableString);
    }

    /**
     * Gets the names of the models used by this query
     * @param \ride\library\database\manipulation\statement\SelectStatement $statement
     * @return array Array with the names of the models as key
     */
    private function getUsedModels(SelectStatement $statement) {
        $usedModels = array();

        $tables = $statement->getTables();
        foreach ($tables as $table) {
            $usedModels[$table->getName()] = true;

            $joins = $table->getJoins();
            foreach ($joins as $join) {
                $modelName = $join->getTable()->getName();
                $usedModels[$modelName] = true;
            }
        }

        return $usedModels;
    }

    /**
     * Add the provided fields to this query with named variables.
     * @param string $expression Field expression
     * @param array $variables Array with the variable name as key and the
     * variable as value
     * @return null
     */
    public function addFieldsWithVariables($expression, array $variables) {
        $expression = $this->parseVariables($expression, $variables);

        parent::addFieldsWithVariables($expression, array());
    }

    /**
     * Adds a join to this query.
     * @param string $type Type of the join
     * @param string $modelName Name of the model to join with
     * @param string $alias Alias for the model to join with
     * @param string $condition Condition string for the join
     * @param array $variables Array with the variable name as key and the
     * variable as value
     * @return null
     */
    public function addJoinWithVariables($type, $modelName, $alias, $condition, array $variables) {
        $condition = $this->parseVariables($condition, $variables);

        parent::addJoinWithVariables($type, $modelName, $alias, $condition, array());
    }

    /**
     * Adds a condition to this query with named variables.
     * @param string $condition Condition string
     * @param array $variables Array with the variable name as key and the
     * variable as value
     * @return null
     */
    public function addConditionWithVariables($condition, array $variables) {
        $condition = $this->parseVariables($condition, $variables);

        parent::addConditionWithVariables($condition, array());
    }

    /**
     * Adds a group by to this query with named variables
     * @param string $expression Group by expression
     * @param array $variables Array with the variable name as key and the
     * variable as value
     * @return null
     */
    public function addGroupByWithVariables($expression, array $variables) {
        $expression = $this->parseVariables($expression, $variables);

        parent::addGroupByWithVariables($expression, array());
    }

    /**
     * Adds a having condition to this query with named variables.
     * @param string $condition Condition string
     * @param array $variables Array with the variable name as key and the
     * variable as value
     * @return null
     */
    public function addHavingWithVariables($condition, array $variables) {
        $condition = $this->parseVariables($condition, $variables);

        parent::addHavingWithVariables($condition, array());
    }

    /**
     * Adds a order by to this query with named variables
     * @param string $expression Order by expression
     * @param array $variables Array with the variable name as key and the
     * variable as value
     * @return null
     */
    public function addOrderByWithVariables($expression, array $variables) {
        $expression = $this->parseVariables($expression, $variables);

        parent::addOrderByWithVariables($expression, array());
    }

    /**
     * Sets the limitation of the query
     * @param integer $count Number of rows to retrieve
     * @param integer $offset Offset of the result
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the provided count
     * or offset is invalid
     */
    public function setLimit($count, $offset = 0) {
        parent::setLimit($count, $offset);

        $this->limitCount = '%_Lc%';
        $this->limitOffset = '%_Lo%';

        $this->variables['_Lc'] = $count;
        $this->variables['_Lo'] = $offset;
    }

    /**
     * Makes sure the variables used in the expression are unique over the
     * complete query and stores the variables in this query.
     * @param string $expression String of a expression
     * @param array $variables Array with the variables used in the condition
     * @return string String of the expression with unique variables
     */
    private function parseVariables($expression, $variables) {
        foreach ($variables as $variable => $value) {
            $newVariable = (count($this->variables) + 1) . '_' . $variable;

            $expression = str_replace('%' . $variable . '%', '%' . $newVariable . '%', $expression);

            $this->variables[$newVariable] = $value;
        }

        return $expression;
    }

    /**
     * Parsed the variables into the provided SQL
     * @param string $sql The SQL
     * @return string The SQL with the parsed variables
     */
    private function parseVariablesIntoSql($sql) {
        $connection = $this->orm->getConnection();
        $statementParser = $connection->getStatementParser();

        foreach ($this->variables as $variable => $value) {
            if ($value instanceof ModelQuery) {
                // subqueries
                $statement = $this->queryParser->parseQuery($value);

                $value = $statementParser->parseStatement($statement);

                $sql = str_replace('%' . $variable . '%', '(' . $value . ')', $sql);
            } elseif (is_object($value)) {
                $sql = str_replace('%' . $variable . '%', $connection->quoteValue($value->id), $sql);
            } elseif (is_array($value)) {
                // array value
                foreach ($value as $k => $v) {
                    $value[$k] = $connection->quoteValue($v);
                }

                $sql = str_replace('%' . $variable . '%', '(' . implode(', ', $value) . ')', $sql);
            } else {
                // scalar value
                $sql = str_replace('%' . $variable . '%', $connection->quoteValue($value), $sql);
            }
        }

        return $sql;
    }

}
