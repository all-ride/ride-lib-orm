<?php

namespace pallo\library\orm\model\behaviour;

use pallo\library\database\manipulation\condition\SimpleCondition;
use pallo\library\database\manipulation\expression\FieldExpression;
use pallo\library\database\manipulation\expression\ScalarExpression;
use pallo\library\database\manipulation\expression\TableExpression;
use pallo\library\database\manipulation\statement\UpdateStatement;
use pallo\library\orm\definition\ModelTable;
use pallo\library\orm\model\data\DatedData;
use pallo\library\orm\model\Model;
use pallo\library\validation\exception\ValidationException;

/**
 * Interface to add extra behaviour to a model
 */
class DatedBehaviour extends AbstractBehaviour {

    /**
     * Hook after creating a data container
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postCreateData(Model $model, $data) {
        if (!$data instanceof DatedData) {
            return;
        }

        $data->setDateAdded();
        $data->setDateModified();
    }

    /**
     * Hook before inserting data
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preInsert(Model $model, $data) {
        if (!$data instanceof DatedData || $data->getDateAdded()) {
            return;
        }

        $data->setDateAdded();
        $data->setDateModified();
    }

    /**
     * Hook before updating data
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preUpdate(Model $model, $data) {
        if (!$data instanceof DatedData) {
            return;
        }

        $data->setDateModified();
    }

    /**
     * Hook after updating a field
     * @param pallo\library\orm\model\Model $model
     * @param integer $id
     * @param string $fieldName
     * @param mixed $value
     * @return null
     */
    public function postUpdateField(Model $model, $id, $fieldName, $value) {
        if (!$model->getMeta()->hasField('dateModified')) {
            return;
        }

        $condition = new SimpleCondition(new FieldExpression(ModelTable::PRIMARY_KEY), new ScalarExpression($id));

        $statement = new UpdateStatement();
        $statement->addTable(new TableExpression($model->getName()));
        $statement->addValue(new FieldExpression('dateModified'), new ScalarExpression(time()));
        $statement->addCondition($condition);

        $connection = $model->getOrmManager()->getConnection();
        $connection->executeStatement($statement);

        $model->clearCache();
    }

}