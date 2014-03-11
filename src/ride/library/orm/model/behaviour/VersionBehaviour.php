<?php

namespace ride\library\orm\model\behaviour;

use ride\library\database\manipulation\condition\SimpleCondition;
use ride\library\database\manipulation\expression\FieldExpression;
use ride\library\database\manipulation\expression\MathematicalExpression;
use ride\library\database\manipulation\expression\ScalarExpression;
use ride\library\database\manipulation\expression\TableExpression;
use ride\library\database\manipulation\statement\UpdateStatement;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\data\VersionedData;
use ride\library\orm\model\Model;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\ValidationError;

/**
 * Interface to add extra behaviour to a model
 */
class VersionBehaviour extends AbstractBehaviour {

    /**
     * Hook after creating a data container
     * @param ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postCreateData(Model $model, $data) {
        if (!$data instanceof VersionedData || $data->getVersion()) {
            return;
        }

        $data->setVersion(0);
    }

    /**
     * Hook before validation of the data
     * @param ride\library\orm\model\Model $model
     * @param ride\library\validation\exception\ValidationException $exception
     * @param mixed $data
     * @return null
     */
    public function postValidate(Model $model, $data, ValidationException $exception) {
        if (!$data instanceof VersionedData || empty($data->id)) {
            return;
        }

        $currentVersion = $this->findVersionById($model, $data->id);
        if ($data->getVersion() == $currentVersion) {
            $data->setVersion($currentVersion + 1);

            return;
        }

        $error = new ValidationError(
            'error.validation.version',
            'Your data is outdated. You are trying to save version %yourVersion% over version %currentVersion%. Try updating your data first.',
            array('yourVersion' => $data->getVersion(), 'currentVersion' => $currentVersion)
        );

        $exception->addErrors('version', array($error));
    }

    /**
     * Hook before inserting data
     * @param ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preInsert(Model $model, $data) {
        if (!$data instanceof VersionedData || $data->getVersion()) {
            return;
        }

        $data->setVersion(1);
    }

    /**
     * Hook after updating a field
     * @param ride\library\orm\model\Model $model
     * @param integer $id
     * @param string $fieldName
     * @param mixed $value
     * @return null
     */
    public function postUpdateField(Model $model, $id, $fieldName, $value) {
        $condition = new SimpleCondition(new FieldExpression(ModelTable::PRIMARY_KEY), new ScalarExpression($id));

        $versionExpression = new FieldExpression('version');
        $mathExpression = new MathematicalExpression();
        $mathExpression->addExpression($versionExpression);
        $mathExpression->addExpression(new ScalarExpression(1));

        $statement = new UpdateStatement();
        $statement->addTable(new TableExpression($model->getName()));
        $statement->addValue($versionExpression, $mathExpression);
        $statement->addCondition($condition);

        $connection = $model->getOrmManager()->getConnection();
        $connection->executeStatement($statement);

        $model->clearCache();
    }

    /**
     * Get the current version of a data object
     * @param int $id primary key of the data
     * @return int the current version of the data object
     */
    private function findVersionById(Model $model, $id) {
        $query = $model->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{version}');
        $query->addCondition('{' . ModelTable::PRIMARY_KEY . '} = %1%', $id);

        $data = $query->queryFirst();

        if (!$data) {
            return 0;
        }

        return $data->getVersion();
    }

}