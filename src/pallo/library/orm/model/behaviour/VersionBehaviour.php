<?php

namespace pallo\library\orm\model\behaviour;

use pallo\library\database\manipulation\condition\SimpleCondition;
use pallo\library\database\manipulation\expression\FieldExpression;
use pallo\library\database\manipulation\expression\MathematicalExpression;
use pallo\library\database\manipulation\expression\ScalarExpression;
use pallo\library\database\manipulation\expression\TableExpression;
use pallo\library\database\manipulation\statement\UpdateStatement;
use pallo\library\orm\definition\ModelTable;
use pallo\library\orm\model\Model;
use pallo\library\validation\exception\ValidationException;
use pallo\library\validation\ValidationError;

/**
 * Interface to add extra behaviour to a model
 */
class VersionBehaviour extends AbstractBehaviour {

    /**
     * Hook after creating a data container
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postCreateData(Model $model, $data) {
        $data->version = 0;
    }

    /**
     * Hook before validation of the data
     * @param pallo\library\orm\model\Model $model
     * @param pallo\library\validation\exception\ValidationException $exception
     * @param mixed $data
     * @return null
     */
    public function postValidate(Model $model, $data, ValidationException $exception) {
        if (empty($data->id)) {
            return;
        }

        $currentVersion = $this->findVersionById($model, $data->id);
        if ($data->version == $currentVersion) {
            $data->version = $data->version + 1;

            return;
        }

        $error = new ValidationError(
            self::TRANSLATION_VALIDATION_ERROR,
			'Your data is outdated. You are trying to save version %yourVersion% over version %currentVersion%. Try updating your data first.',
            array('yourVersion' => $data->version, 'currentVersion' => $currentVersion)
        );

        $validationException->addErrors('version', array($error));
    }

    /**
     * Hook before inserting data
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preInsert(Model $model, $data) {
        if (!empty($data->version)) {
            return;
        }

        $data->version = 1;
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
        $condition = new SimpleCondition(new FieldExpression(ModelTable::PRIMARY_KEY), new ScalarExpression($id));

        $versionExpression = new FieldExpression(self::NAME);
        $mathExpression = new MathematicalExpression();
        $mathExpression->addExpression($versionExpression);
        $mathExpression->addExpression(new ScalarExpression(1));

        $statement = new UpdateStatement();
        $statement->addTable(new TableExpression($this->model->getName()));
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

        return $data->version;
    }

}