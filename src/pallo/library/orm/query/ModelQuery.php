<?php

namespace pallo\library\orm\query;

use pallo\library\database\manipulation\condition\Condition;
use pallo\library\orm\definition\field\BelongsToField;
use pallo\library\orm\definition\field\HasField;
use pallo\library\orm\definition\ModelTable as DefinitionModelTable;
use pallo\library\orm\exception\OrmException;
use pallo\library\orm\model\meta\ModelMeta;
use pallo\library\orm\model\LocalizedModel;
use pallo\library\orm\model\Model;
use pallo\library\orm\query\parser\QueryParser;
use pallo\library\orm\query\tokenizer\FieldTokenizer;
use pallo\library\orm\OrmManager;

use \Exception;

/**
 * ORM model query
 */
class ModelQuery {

    /**
     * Instance of the model manager
     * @var pallo\library\orm\OrmManager
     */
    protected $orm;

    /**
     * The model of this query
     * @var pallo\library\orm\model\Model
     */
    protected $model;

    /**
     * Logical operator for the conditions
     * @var string
     */
    protected $operator;

    /**
     * Flag to fetch only distinctive rows
     * @var boolean
     */
    protected $distinct;

    /**
     * Depth for fetching relations
     * @var integer
     */
    protected $recursiveDepth;

    /**
     * Locale code for the data
     * @var string
     */
    protected $locale;

    /**
     * Flag to set whether to include unlocalized data in the result
     * @var boolean
     */
    protected $includeUnlocalizedData;

    /**
     * Flag to set whether to fetch unlocalized data in the result
     * @var boolean
     */
    protected $fetchUnlocalizedData;

    /**
     * Flag to set whether to add a is localized order
     * @var boolean
     */
    protected $addIsLocalizedOrder;

    /**
     * Array with field strings
     * @var array
     */
    protected $fields;

    /**
     * Array with ModelJoin objects
     * @var array
     */
    protected $joins;

    /**
     * Array with ModelExpression objects
     * @var array
     */
    protected $conditions;

    /**
     * Array with group by strings
     * @var array
     */
    protected $groupBy;

    /**
     * Array with ModelExpression objects
     * @var array
     */
    protected $having;

    /**
     * Array with order by strings
     * @var array
     */
    protected $orderBy;

    /**
     * Number of rows to fetch
     * @var integer
     */
    protected $limitCount;

    /**
     * Offset for the result
     * @var integer
     */
    protected $limitOffset;

    /**
     * Tokenizer to extract the field expressions from a statement
     * @var pallo\library\orm\query\tokenizer\FieldTokenizer
     * @static
     */
    protected $fieldTokenizer;

    /**
     * Parser to parse ModelQuery objects into SelectStatement objects
     * @var pallo\library\orm\query\parser\QueryParser
     * @static
     */
    protected $queryParser;

    /**
     * Constructs a new model query
     * @param pallo\library\orm\model\Model $model Instance of the model for
     * this query
     * @param array $locales Array with the available locales, locale code as
     * key
     * @param string $locale The locale for the query
     * @return null
     */
    public function __construct(Model $model, array $locales, $locale) {
        $this->setLocale($locale);

        $this->orm = $model->getOrmManager();
        $this->model = $model;

        $this->fieldTokenizer = $this->orm->getFieldTokenizer();
        $this->queryParser = $this->orm->getQueryParser();
        $this->locales = $locales;

        $this->recursiveDepth = 1;
        $this->includeUnlocalizedData = false;
        $this->fetchUnlocalizedData = false;
        $this->addIsLocalizedOrder = false;

        $this->distinct = false;
        $this->operator = Condition::OPERATOR_AND;

        $this->fields = false;
        $this->joins = array();
        $this->conditions = array();
        $this->groupBy = array();
        $this->having = array();
        $this->orderBy = array();
    }

    /**
     * Gets the model of this query
     * @return pallo\library\orm\model\Model
     */
    public function getModel() {
        return $this->model;
    }

    /**
     * Sets the logical operator used between the conditions
     * @param string $operator Logical operator AND or OR
     * @return null
     * @throws pallo\library\orm\exception\OrmException when an invalid operator is provided
     */
    public function setOperator($operator = null) {
        if (is_null($operator)) {
            $operator = Condition::OPERATOR_AND;
        }

        if ($operator != Condition::OPERATOR_AND && $operator != Condition::OPERATOR_OR) {
            throw new OrmException('Invalid operator provided, try AND or OR');
        }

        $this->operator = $operator;
    }

    /**
     * Gets the logical operator used between the condition
     * @return string AND or OR
     */
    public function getOperator() {
        return $this->operator;
    }

    /**
     * Sets the locale of the data to retrieve
     * @param string $locale Code of the locale to use
     * @return null
     */
    public function setLocale($locale) {
        if (!is_string($locale) || $locale == '') {
            throw new OrmException('Provided locale is empty or not a string');
        }

        $this->locale = $locale;
    }

    /**
     * Gets the locale of the data to retrieve
     * @return string Code of the locale to use
     */
    public function getLocale() {
        return $this->locale;
    }

    /**
     * Sets whether to include unlocalized data
     * @param boolean|string $flag True to include the unlocalized data, false
     * to omit the unlocalized data
     * @return null
     */
    public function setIncludeUnlocalizedData($flag) {
        $this->includeUnlocalizedData = $flag;
    }

    /**
     * Gets whether to unclude unlocalized data
     * @return boolean True to include unlocalized data, false otherwise
     */
    public function willIncludeUnlocalizedData() {
        return $this->includeUnlocalizedData;
    }

    /**
     * Sets whether to fetch unlocalized data. if the data is not localized,
     * the next locale will be queried until a localized version of the data
     * is retrieved.
     * @param boolean|string $flag True to fetch the unlocalized data, false
     * otherwise
     * @return null
     */
    public function setFetchUnlocalizedData($flag) {
        $this->fetchUnlocalizedData = $flag;

        if ($flag) {
            $this->includeUnlocalizedData = $flag;
        }
    }

    /**
     * Gets whether to fetch unlocalized data
     * @return boolean True to fetch unlocalized data, false otherwise
     */
    public function willFetchUnlocalizedData() {
        return $this->fetchUnlocalizedData;
    }

    /**
     * Sets whether to add a is localized order. This will only be done when there is need for a localized join
     * @param boolean|string $flag True to add the order expression, false otherwise
     * @return null
     */
    public function setWillAddIsLocalizedOrder($flag) {
        $this->addIsLocalizedOrder = $flag;
    }

    /**
     * Gets whether to add a is localized order
     * @return boolean True to add the order expression, false otherwise
     */
    public function willAddIsLocalizedOrder() {
        return $this->addIsLocalizedOrder;
    }

    /**
     * Sets the to retrieve only distinctive rows
     * @param boolean $distinct
     * @return null
     */
    public function setDistinct($distinct) {
        $this->distinct = $distinct;
    }

    /**
     * Gets whether to retrieve only distinctive rows
     * @return boolean
     */
    public function isDistinct() {
        return $this->distinct;
    }

    /**
     * Sets the depth of relations to fetch
     * @param integer $depth Null to retrieve the full data, a positive number to define
     * the depth of relations to fetch
     * @return null
     * @
     */
    public function setRecursiveDepth($depth = null) {
        if ($depth !== null && (!is_numeric($depth) || $depth < 0)) {
            throw new OrmException('Provided depth is not a positive integer');
        }

        $this->recursiveDepth = $depth;
    }

    /**
     * Gets the depth of relations to fetch
     * @return null|integer Null to retrieve the full data, the depth of relations to fetch otherwise
     */
    public function getRecursiveDepth() {
        return $this->recursiveDepth;
    }

    /**
     * Gets the SQL of the count query
     * @return string The SQL of the count query
     */
    public function getCountSql() {
        $statement = $this->queryParser->parseQueryForCount($this);

        $connection = $this->orm->getConnection();
        $statementParser = $connection->getStatementParser();

        return $statementParser->parseStatement($statement);
    }

    /**
     * Gets the SQL of the result query
     * @return string The SQL of the result query
     */
    public function getQuerySql() {
        $statement = $this->queryParser->parseQuery($this);

        $connection = $this->orm->getConnection();
        $statementParser = $connection->getStatementParser();

        return $statementParser->parseStatement($statement);
    }

    /**
     * Counts the results for this query
     * @return integer number of rows in this query
     */
    public function count() {
        $statement = $this->queryParser->parseQueryForCount($this);

        $connection = $this->orm->getConnection();
        $result = $connection->executeStatement($statement);

        if ($result->getRowCount()) {
            $row = $result->getFirst();

            return $row[QueryParser::ALIAS_COUNT];
        }

        return 0;
    }

    /**
     * Queries for the first row of this query
     * @return mixed A data object when there was a result, null otherwise
     */
    public function queryFirst() {
        $this->setLimit(1, 0);

        $result = $this->query();

        if (empty($result)) {
            return null;
        }

        return array_shift($result);
    }

    /**
     * Executes this query and returns the result
     * @param string $parse Set to false to skip result parsing, set to a name
     * of a field to use that value as key in the resulting array, default is
     * the primary key
     * @return array Array with data objects
     */
    public function query($parse = null) {
        $statement = $this->queryParser->parseQuery($this);
        $belongsToFields = $this->queryParser->getRecursiveBelongsToFields();
        $hasFields = $this->queryParser->getRecursiveHasFields();

        $connection = $this->orm->getConnection();
        $result = $connection->executeStatement($statement);

        if ($parse !== false) {
            $result = $this->parseResult($result, $belongsToFields, $hasFields, $parse);
        }

        return $result;
    }

    /**
     * Parses the result to fetch the relations and the unlocalized data
     * @param array $result
     * @param array $belongsToFields
     * @param array $hasFields
     * @param string $indexField Name of the field to use as key in the resulting array
     * @return array Result
     */
    protected function parseResult($result, $belongsToFields, $hasFields, $indexField = null) {
        $result = $this->model->getResultParser()->parseResult($this->orm, $result, $indexField);

        $result = $this->queryRelations($result, $belongsToFields, $hasFields);

        $result = $this->queryUnlocalized($result, $indexField);

        return $result;
    }

    /**
     * Query the unlocalized data in the first localized data from the locales list
     * @param array $result The result of this query
     * @param string $indexField
     * @return array The result with the unlocalized data queried in the first localized data from the locales list
     */
    protected function queryUnlocalized($result, $indexField = null) {
        if (!$this->fetchUnlocalizedData || !$this->model->getMeta()->isLocalized()) {
            return $result;
        }

        $unlocalizedResult = array();
        foreach ($result as $index => $data) {
            if ($data->isDataLocalized || !isset($data->id)) {
                continue;
            }

            $unlocalizedResult[$data->id] = $data->id;
        }

        if (!$unlocalizedResult) {
            return $result;
        }

        foreach ($this->locales as $locale => $null) {
            if ($locale == $this->locale) {
                continue;
            }

            $condition = '{id} IN (' . implode(', ', $unlocalizedResult) . ')';

            $query = clone($this);
            $query->setLocale($locale);
            $query->setIncludeUnlocalizedData(false);
            $query->setFetchUnlocalizedData(false);
            $query->setLimit(0);
            $query->addCondition($condition);
            $query->removeOrderBy();

            $localeResult = $query->query($indexField);

            foreach ($localeResult as $index => $localeData) {
                $localeData->dataLocale = $locale;

                $result[$index] = $localeData;

                unset($unlocalizedResult[$localeData->id]);
            }

            if (!$unlocalizedResult) {
                break;
            }
        }

        return $result;
    }

    /**
     * Queries the relations of the result
     * @param array $result Model query result
     * @return array Model query result with fetched relations
     */
    protected function queryRelations(array $result, array $belongsToFields, array $hasFields) {
        if ($this->recursiveDepth === 0 || $this->recursiveDepth === '0') {
            return $result;
        }

        if ($this->recursiveDepth === null) {
            $recursiveDepth = null;
        } else {
            $recursiveDepth = $this->recursiveDepth - 1;
        }

        $meta = $this->model->getMeta();
        $localizedFields = array();

        if ($belongsToFields) {
            foreach ($belongsToFields as $fieldName => $field) {
                $result = $this->queryBelongsTo($result, $meta, $recursiveDepth, $fieldName, $field);
            }
        } elseif ($this->fetchUnlocalizedData) {
            $locale = $this->getLocale();

            $belongsToFields = $meta->getBelongsTo();
            foreach ($belongsToFields as $fieldName => $field) {
                $relationModelName = $meta->getRelationModelName($fieldName);
                $relationModel = $this->orm->getModel($relationModelName);

                if (!$relationModel->getMeta()->isLocalized()) {
                    continue;
                }

                foreach ($result as $index => $data) {
                    if (!isset($data->$fieldName) || (!isset($data->$fieldName->id) || !$data->$fieldName->id) || (isset($data->$fieldName->dataLocale) && $data->$fieldName->dataLocale == $locale)) {
                        continue;
                    }

                    $query = $relationModel->createQuery($locale);
                    $query->setRecursiveDepth($recursiveDepth);
                    $query->setFetchUnlocalizedData(true);
                    $query->addCondition('{id} = %1%', $data->$fieldName->id);

                    $result[$index]->$fieldName = $query->queryFirst();
                }
            }
        }

        foreach ($hasFields as $fieldName => $field) {
            if ($field->isLocalized()) {
                $localizedFields[] = $fieldName;
            } else {
                $result = $this->queryHas($result, $meta, $recursiveDepth, $fieldName, $field);
            }
        }

        if (!empty($localizedFields)) {
            $result = $this->queryLocalizedHas($result, $recursiveDepth, $localizedFields);
        }

        return $result;
    }

    /**
     * Queries for localized has relation fields and sets the result to the provided result
     * @param array $result Model query result
     * @param integer $recursiveDepth Recursive depth for the queries
     * @param array $localizedFields Array with field names
     * @return array Model query result with the localized fields
     */
    private function queryLocalizedHas(array $result, $recursiveDepth, array $localizedFields) {
        if ($recursiveDepth !== null) {
            $recursiveDepth++;
        }

        $locale = $this->getLocale();

        $fields = '{id}';
        foreach ($localizedFields as $fieldName) {
            $fields .= ', {' . $fieldName . '}';
        }

        foreach ($result as $id => $data) {
            $localizedModelName = $this->model->getMeta()->getLocalizedModelName();
            $localizedModel = $this->orm->getModel($localizedModelName);

            $localizedData = $localizedModel->getLocalizedData($id, $locale, $recursiveDepth, $fields);

            foreach ($localizedFields as $fieldName) {
                if (!isset($localizedData->$fieldName)) {
                    continue;
                }

                $result[$id]->$fieldName = $localizedData->$fieldName;
            }
        }

        return $result;
    }

    /**
     * Queries for belongs to relation fields and sets the result to the provided result
     * @param array $result Model query result
     * @param integer $recursiveDepth Recursive depth for the queries
     * @param string $fieldName Name of the has field
     * @param pallo\library\orm\definition\field\BelongsToField $field Definition of the field
     * @return array Model query result with the belongs to fields
     */
    private function queryBelongsTo(array $result, ModelMeta $meta, $recursiveDepth, $fieldName, BelongsToField $field) {
        $locale = $this->getLocale();

        $relationModelName = $meta->getRelationModelName($fieldName);
        $relationModel = $this->orm->getModel($relationModelName);

        foreach ($result as $index => $data) {
            if (!$data->$fieldName) {
                continue;
            }

            $query = $relationModel->createQuery($locale);
            $query->setRecursiveDepth($recursiveDepth);
            $query->setIncludeUnlocalizedData($this->includeUnlocalizedData);
            $query->setFetchUnlocalizedData($this->fetchUnlocalizedData);
            $query->addCondition('{id} = %1%', $data->$fieldName);

            $result[$index]->$fieldName = $query->queryFirst();
        }

        return $result;
    }

    /**
     * Queries for has relation fields and sets the result to the provided result
     * @param array $result Model query result
     * @param integer $recursiveDepth Recursive depth for the queries
     * @param string $fieldName Name of the has field
     * @param pallo\library\orm\definition\field\HasField $field Definition of the field
     * @return array Model query result with the has fields
     */
    private function queryHas(array $result, ModelMeta $meta, $recursiveDepth, $fieldName, HasField $field) {
        $linkModelName = $meta->getRelationLinkModelName($fieldName);
        if (!$linkModelName) {
            return $this->queryHasWithoutLinkModel($result, $meta, $recursiveDepth, $fieldName, $field);
        }

        if ($recursiveDepth !== null) {
            $recursiveDepth++;
        }

        if ($meta->isRelationWithSelf($fieldName)) {
            return $this->queryHasWithLinkModelToSelf($result, $meta, $recursiveDepth, $fieldName, $field);
        }

        return $this->queryHasWithLinkModel($result, $meta, $recursiveDepth, $fieldName, $field);
    }

    /**
     * Queries for has relation fields without a link model and sets the result to the provided result
     * @param array $result Model query result
     * @param integer $recursiveDepth Recursive depth for the queries
     * @param string $fieldName Name of the has field
     * @param pallo\library\orm\definition\field\HasField $field Definition of the field
     * @return array Model query result with the has fields
     */
    private function queryHasWithoutLinkModel(array $result, ModelMeta $meta, $recursiveDepth, $fieldName, HasField $field) {
        $locale = $this->getLocale();

        $modelName = $meta->getRelationModelName($fieldName);
        $model = $this->orm->getModel($modelName);

        $foreignKey = $meta->getRelationForeignKey($fieldName);

        $isHasOne = $field instanceof HasOneField;
        $indexOn = null;
        if (!$isHasOne) {
            $indexOn = $field->getIndexOn();
        }

        foreach ($result as $index => $data) {
            $query = $model->createQuery($locale);
            $query->setRecursiveDepth($recursiveDepth);
            $query->setIncludeUnlocalizedData($this->includeUnlocalizedData);
            $query->setFetchUnlocalizedData($this->fetchUnlocalizedData);
            $query->removeFields('{' . $foreignKey . '}');
            $query->addCondition('{' . $foreignKey . '} = %1%', $data->id);

            if ($isHasOne) {
                $result[$index]->$fieldName = $this->queryHasOneWithoutLinkModel($query, $foreignKey, $data);
            } else {
                $result[$index]->$fieldName = $this->queryHasManyWithoutLinkModel($query, $meta, $fieldName, $foreignKey, $data, $indexOn);
            }
        }

        return $result;
    }

    /**
     * Queries for has one relation fields without a link model
     * @param ModelQuery $query Query for the has one data
     * @param string $foreignKey Name of the foreign key field
     * @param mixed $data Data which will contain the has one result
     * @return array Model query result for the has one field
     */
    private function queryHasOneWithoutLinkModel(ModelQuery $query, $foreignKey, $data) {
        $queryResult = $query->queryFirst();

        if ($query->getRecursiveDepth() === 0) {
            $queryResult->$foreignKey = $data->id;
        } else {
            $queryResult->$foreignKey = $data;
        }

        return $queryResult;
    }

    /**
     * Queries for has many relation fields without a link model
     * @param ModelQuery $query Query for the has many data
     * @param string $foreignKey Name of the foreign key field
     * @param mixed $data Data which will contain the has many result
     * @return array Model query result for the has many field
     */
    private function queryHasManyWithoutLinkModel(ModelQuery $query, ModelMeta $meta, $fieldName, $foreignKey, $data, $indexOn = null) {
        $recursiveDepth = $query->getRecursiveDepth();

        $order = $meta->getRelationOrder($fieldName);
        if ($order != null) {
            $query->addOrderBy($order);
        }

        $queryResult = $query->query($indexOn);
        foreach ($queryResult as $queryIndex => $queryData) {
            if ($recursiveDepth === 0) {
                $queryData->$foreignKey = $data->id;
            } else {
                $queryData->$foreignKey = $data;
            }
        }

        return $queryResult;
    }

    /**
     * Queries for has relation fields with a link model and sets the result to the provided result
     * @param array $result Model query result
     * @param integer $recursiveDepth Recursive depth for the queries
     * @param string $fieldName Name of the has field which will contain the result
     * @param pallo\library\orm\definition\field\HasField $field Definition of the field
     * @return array Model query result with the has fields
     */
    private function queryHasWithLinkModel(array $result, ModelMeta $meta, $recursiveDepth, $fieldName, HasField $field) {
        $locale = $this->getLocale();

        $linkModelName = $meta->getRelationLinkModelName($fieldName);
        $linkModel = $this->orm->getModel($linkModelName);

        $foreignKey = $meta->getRelationForeignKey($fieldName);
        $foreignKeyToSelf = $meta->getRelationForeignKeyToSelf($fieldName);

        $isHasOne = $field instanceof HasOneField;

        $reflectionHelper = $this->model->getReflectionHelper();

        foreach ($result as $index => $data) {
            $query = $linkModel->createQuery($locale);
            $query->setRecursiveDepth($recursiveDepth);
            $query->setIncludeUnlocalizedData($this->includeUnlocalizedData);
            $query->setFetchUnlocalizedData($this->fetchUnlocalizedData);
            $query->setOperator(Condition::OPERATOR_OR);
            $query->setFields('{id}, {' . $foreignKey . '}');
            $query->addCondition('{' . $foreignKeyToSelf . '} = %1%', $reflectionHelper->getProperty($data, DefinitionModelTable::PRIMARY_KEY));

            if ($isHasOne) {
                $reflectionHelper->setProperty($result[$index], $fieldName, $this->queryHasOneWithLinkModel($query, $foreignKey));
            } else {
                $reflectionHelper->setProperty($result[$index], $fieldName, $this->queryHasManyWithLinkModel($query, $meta, $fieldName, $foreignKey));
            }
        }

        return $result;
    }

    /**
     * Queries for has one relation fields with a link model
     * @param ModelQuery $query Query for the has many data
     * @param string $foreignKey Name of the foreign key
     * @return array Model query result for the has one field
     */
    private function queryHasOneWithLinkModel(ModelQuery $query, $foreignKey) {
        $data = $query->queryFirst();

        if (!isset($data->$foreignKey)) {
            return null;
        }

        return $data->$foreignKey;
    }

    /**
     * Queries for has many relation fields with a link model
     * @param ModelQuery $query Query for the has many data
     * @param string $fieldName Name of the field which will contain the has many result
     * @param string $foreignKey Name of the foreign key
     * @return array Model query result for the has many field
     */
    private function queryHasManyWithLinkModel(ModelQuery $query, ModelMeta $meta, $fieldName, $foreignKey) {
        if ($query->getRecursiveDepth() == 1) {
            $order = $meta->getRelationOrder($fieldName);
            if ($order != null) {
                $query->addOrderBy($order);
            }
        }

        $result = array();

        $queryResult = $query->query();
        foreach ($queryResult as $data) {
            if (!isset($data->$foreignKey)) {
                continue;
            }

            if (isset($data->$foreignKey->id)) {
                $result[$data->$foreignKey->id] = $data->$foreignKey;
            } else {
                $result[] = $data->$foreignKey;
            }
        }

        return $result;
    }

    /**
     * Queries for has relation fields with a link model to self and sets the result to the provided result
     * @param array $result Model query result
     * @param integer $recursiveDepth Recursive depth for the queries
     * @param string $fieldName Name of the has field
     * @param pallo\library\orm\definition\field\HasField $field Definition of the field
     * @return array Model query result with the has fields
     */
    private function queryHasWithLinkModelToSelf(array $result, ModelMeta $meta, $recursiveDepth, $fieldName, HasField $field) {
        $locale = $this->getLocale();

        $foreignKeys = $meta->getRelationForeignKey($fieldName);

        $linkModelName = $meta->getRelationLinkModelName($fieldName);
        $linkModel = $this->orm->getModel($linkModelName);

        $isHasOne = $field instanceof HasOneField;

        foreach ($result as $index => $data) {
            $query = $linkModel->createQuery($locale);
            $query->setRecursiveDepth($recursiveDepth);
            $query->setIncludeUnlocalizedData($this->includeUnlocalizedData);
            $query->setFetchUnlocalizedData($this->fetchUnlocalizedData);
            $query->setOperator(Condition::OPERATOR_OR);

            foreach ($foreignKeys as $foreignKey) {
                $query->addCondition('{' . $foreignKey->getName() . '} = %1%', $data->id);
            }

            if ($isHasOne) {
                $result[$index]->$fieldName = $this->queryHasOneWithLinkModelToSelf($query, $foreignKeys, $data->id);
            } else {
                $result[$index]->$fieldName = $this->queryHasManyWithLinkModelToSelf($query, $meta, $fieldName, $foreignKeys, $data->id);
            }
        }

        return $result;
    }

    /**
     * Queries for has one relation fields with a link model to self
     * @param ModelQuery $query Query for the has many data
     * @param array $foreignKeys Array with ModelField objects
     * @param integer $id Primary key of the data which will contain the has many data
     * @return array Model query result for the has one field
     */
    private function queryHasOneWithLinkModelToSelf(ModelQuery $query, array $foreignKeys, $id) {
        $data = $query->queryFirst();

        if (!$data) {
            return null;
        }

        foreach ($foreignKeys as $foreignKey) {
            if (isset($data->$foreignKey) && $data->$foreignKey != $id) {
                return $data->$foreignKey;
            }
        }

        return null;
    }

    /**
     * Queries for has one relation fields with a link model to self
     * @param ModelQuery $query Query for the has many data
     * @param string $fieldName Name of the field which will contain the has many data
     * @param array $foreignKeys Array with ModelField objects
     * @param integer $id Primary key of the data which will contain the has many data
     * @return array Model query result for the has many field
     */
    private function queryHasManyWithLinkModelToSelf(ModelQuery $query, ModelMeta $meta, $fieldName, array $foreignKeys, $id) {
        $order = $meta->getRelationOrder($fieldName);
        if ($order != null) {
            $query->addOrderBy($order);
        }

        $result = array();

        $queryResult = $query->query();
        foreach ($queryResult as $data) {
            foreach ($foreignKeys as $foreignKey) {
                if ($data->$foreignKey->id != $id) {
                    break;
                }
            }

            $result[$data->$foreignKey->id] = $data->$key;
         }

         return $result;
    }

    /**
     * Adds the provided fields to this query. Extra arguments are viewed as variables.
     *
     * Arguments passed after the expression will be viewed as variables for it. The name of these
     * variables is the index in the argument list: numeric starting from 1.
     * @param string $expression Field expression
     * @return null
     */
    public function addFields($expression) {
        $this->setModelFields();

        $variables = array_slice(func_get_args(), 1, null, true);

        $this->addFieldsWithVariables($expression, $variables);
    }

    /**
     * Add the provided fields to this query with named variables.
     * @param string $expression Field expression
     * @param array $variables Array with the variable name as key and the variable as value
     * @return null
     */
    public function addFieldsWithVariables($expression, array $variables) {
        $this->fields[] = new ModelExpression($expression, $variables);
    }

    /**
     * Sets the provided fields as the fields of this query. Extra arguments are viewed as variables.
     *
     * Arguments passed after the expression will be viewed as variables for it. The name of these
     * variables is the index in the argument list: numeric starting from 1.
     * @return null
     */
    public function setFields($expression) {
        $this->fields = array();

        $variables = array_slice(func_get_args(), 1, null, true);

        $this->addFieldsWithVariables($expression, $variables);
    }

    /**
     * Sets all the fields of the model to this query, only if the fields of this query are not yet initialized.
     * @return null
     */
    private function setModelFields() {
        if ($this->fields !== false) {
            return;
        }

        $this->fields = array();

        $fields = $this->model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            $this->fields[] = new ModelExpression('{' . $fieldName . '}');
        }
    }

    /**
     * Removes the provided fields from this query.
     * @return null
     */
    public function removeFields($expression) {
        $this->setModelFields();

        $expressions = $this->fieldTokenizer->tokenize($expression);
        foreach ($expressions as $expression) {
            foreach ($this->fields as $index => $fieldExpression) {
                if ($fieldExpression->getExpression() == $expression) {
                    unset($this->fields[$index]);

                    return;
                }
            }

            throw new OrmException('Provided expression ' . $expression . ' was not found in this query');
        }
    }

    /**
     * Gets the fields for this query
     * @return array Array with a ModelExpression objects
     */
    public function getFields() {
        $this->setModelFields();

        return $this->fields;
    }

    /**
     * Adds a join to this query. Extra arguments are viewed as variables for the condition.
     *
     * Arguments passed after the condition will be viewed as variables for it. The name of these
     * variables is the index in the argument list: numeric starting from 1.
     * @param string $type Type of the join
     * @param string $modelName Name of the model to join with
     * @param string $alias Alias for the model to join with
     * @param string $condition Condition string for the join
     * @return null
     */
    public function addJoin($type, $modelName, $alias, $condition) {
        $variables = array_slice(func_get_args(), 4);

        if ($variables) {
            $tmpVariables = array();
            $index = 1;
            foreach ($variables as $value) {
                $tmpVariables[$index] = $value;
                $index++;
            }

            $variables = $tmpVariables;
        }

        $this->addJoinWithVariables($type, $modelName, $alias, $condition, $variables);
    }

    /**
     * Adds a join to this query.
     * @param string $type Type of the join
     * @param string $modelName Name of the model to join with
     * @param string $alias Alias for the model to join with
     * @param string $condition Condition string for the join
     * @param array $variables Array with the variable name as key and the variable as value
     * @return null
     */
    public function addJoinWithVariables($type, $modelName, $alias, $condition, array $variables) {
        $table = new ModelTable($modelName, $alias);

        $condition = new ModelExpression($condition, $variables);

        $this->joins[$alias] = new ModelJoin($type, $table, $condition);
    }

    /**
     * Gets the joins of this query
     * @return array Array with ModelJoin objects
     */
    public function getJoins() {
        return $this->joins;
    }

    /**
     * Adds a condition to this query. Extra arguments are viewed as variables.
     *
     * Arguments passed after the condition will be viewed as variables. The name of these variables
     * is the index in the argument list: numeric starting from 1.
     * @param string $condition Condition string
     * @return null
     */
    public function addCondition($condition) {
        $variables = array_slice(func_get_args(), 1, null, true);

        $this->addConditionWithVariables($condition, $variables);
    }

    /**
     * Adds a condition to this query with named variables.
     * @param string $condition Condition string
     * @param array $variables Array with the variable name as key and the variable as value
     * @return null
     */
    public function addConditionWithVariables($condition, array $variables) {
        $this->conditions[] = new ModelExpression($condition, $variables);
    }

    /**
     * Gets the conditions of this query
     * @return array Array with ModelExpression objects
     */
    public function getConditions() {
        return $this->conditions;
    }

    /**
     * Clears all the conditions of this query
     * @return null
     */
    public function clearConditions() {
        $this->conditions = array();
    }

    /**
     * Adds a group by to this query. Extra arguments are viewed as variables.
     *
     * Arguments passed after the expression will be viewed as variables for it. The name of these
     * variables is the index in the argument list: numeric starting from 1.
     * @param string $expression Expression for the group by
     * @return null
     */
    public function addGroupBy($expression) {
        $variables = array_slice(func_get_args(), 1, null, true);

        $this->addGroupByWithVariables($expression, $variables);
    }

    /**
     * Adds a group by to this query with named variables
     * @param string $expression Group by expression
     * @param array $variables Array with the variable name as key and the variable as value
     * @return null
     */
    public function addGroupByWithVariables($expression, array $variables) {
        $this->groupBy[] = new ModelExpression($expression, $variables);
    }

    /**
     * Gets the group by tokens
     * @return array Array with ModelExpression objects
     */
    public function getGroupBy() {
        return $this->groupBy;
    }

    /**
     * Adds a having condition to this query. Extra arguments are viewed as variables.
     *
     * Arguments passed after the condition will be viewed as variables. The name of these variables
     * is the index in the argument list: numeric starting from 1.
     * @param string $condition Condition string
     * @return null
     */
    public function addHaving($condition) {
        $variables = array_slice(func_get_args(), 1, null, true);

        $this->addHavingWithVariables($condition, $variables);
    }

    /**
     * Adds a having condition to this query with named variables.
     * @param string $condition Condition string
     * @param array $variables Array with the variable name as key and the variable as value
     * @return null
     */
    public function addHavingWithVariables($condition, array $variables) {
        $this->having[] = new ModelExpression($condition, $variables);
    }

    /**
     * Gets the having conditions of this query
     * @return array Array with ModelExpression objects
     */
    public function getHaving() {
        return $this->having;
    }

    /**
     * Adds a order by to this query. Extra arguments are viewed as variables.
     *
     * Arguments passed after the expression will be viewed as variables for it. The name of these
     * variables is the index in the argument list: numeric starting from 1.
     * @param string $expression Order by expression
     * @return null
     */
    public function addOrderBy($expression) {
        $variables = array_slice(func_get_args(), 1, null, true);

        $this->addOrderByWithVariables($expression, $variables);
    }

    /**
     * Adds a order by to this query with named variables
     * @param string $expression Order by expression
     * @param array $variables Array with the variable name as key and the variable as value
     * @return null
     */
    public function addOrderByWithVariables($expression, array $variables) {
        $this->orderBy[] = new ModelExpression($expression, $variables);
    }

    /**
     * Gets the order by strings
     * @return array Array with a  order by as value
     */
    public function getOrderBy() {
        return $this->orderBy;
    }

    /**
     * Removes all the order by from this query
     * @return null
     */
    public function removeOrderBy() {
        $this->orderBy = array();
    }

    /**
     * Sets the limitation of the query
     * @param integer $count Number of rows to retrieve
     * @param integer $offset Offset of the result
     * @return null
     * @throws pallo\library\orm\exception\OrmException when the provided count
     * or offset is invalid
     */
    public function setLimit($count, $offset = 0) {
        if (!is_integer($count) || $count < 0) {
            throw new OrmException('Provided count should be a positive number');
        }

        if (!is_integer($offset) || $offset < 0) {
            throw new OrmException('Provided offset should be a positive number or zero');
        }

        $this->limitCount = $count;
        $this->limitOffset = $offset;
    }

    /**
     * Gets the number of rows to retrieve
     * @return integer
     */
    public function getLimitCount() {
        return $this->limitCount;
    }

    /**
     * Gets the offset of the result
     * @return integer
     */
    public function getLimitOffset() {
        return $this->limitOffset;
    }

}