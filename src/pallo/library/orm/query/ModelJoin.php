<?php

namespace pallo\library\orm\query;

/**
 * Definition of a join for a ModelQuery
 */
class ModelJoin {

    /**
     * Type of this join
     * @var string
     */
    private $type;

    /**
     * Table to join with
     * @var ModelTable
     */
    private $table;

    /**
     * Condition to use for the join
     * @var ModelExpression
     */
    private $condition;

    /**
     * Constructs a new model join
     * @param string $type Join type (INNER, LEFT, RIGHT)
     * @param ModelTable $table Table to join with
     * @param ModelExpression $condition Condition to use for the join
     * @return null
     */
    public function __construct($type, ModelTable $table, ModelExpression $condition) {
        $this->type = $type;
        $this->table = $table;
        $this->condition = $condition;
    }

    /**
     * Gets the type of this join
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Gets the table to join with
     * @return ModelTable
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * Gets the condition to use for the join
     * @return ModelExpression
     */
    public function getCondition() {
        return $this->condition;
    }

}