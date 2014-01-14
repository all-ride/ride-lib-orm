<?php

namespace pallo\library\orm\model\behaviour;

use pallo\library\orm\definition\ModelTable;
use pallo\library\orm\model\LocalizedModel;
use pallo\library\orm\model\LogModel;
use pallo\library\orm\model\Model;

/**
 * Interface to add extra behaviour to a model
 */
class LogBehaviour extends AbstractBehaviour {

    /**
     * Container of the old data
     * @var array
     */
    private $oldData;

    /**
     * Hook after inserting data
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postInsert(Model $model, $data) {
        $logFields = $this->getLogFields($model, $data);

        $logModel = $model->getOrmManager()->getModel(LogModel::NAME);
        $logModel->logInsert($model->getName(), $logFields, $data);
    }

    /**
     * Hook before updating data
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preUpdate(Model $model, $data) {
        $localeField = LocalizedModel::FIELD_LOCALE;

        $locale = null;
        if (isset($data->$localeField)) {
            $locale = $data->$localeField;
        }

        $modelName = $model->getName();

        if (!isset($this->oldData)) {
            $this->oldData = array($modelName => array());
        } elseif (!isset($this->oldData[$modelName])) {
            $this->oldData[$modelName] = array();
        }

        $id = $model->getReflectionHelper()->getProperty($data, ModelTable::PRIMARY_KEY);

        $query = $model->createQuery($locale);
        $query->setIncludeUnlocalizedData(true);
        $query->addCondition('{id} = %1%', $id);

        $this->oldData[$modelName][$id] = $query->queryFirst();
    }

    /**
     * Hook after updating data
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postUpdate(Model $model, $data) {
        $logFields = $this->getLogFields($model, $data);

        $modelName = $model->getName();
        $id = $model->getReflectionHelper()->getProperty($data, ModelTable::PRIMARY_KEY);

        $oldData = null;
        if (isset($this->oldData[$modelName][$id])) {
            $oldData = $this->oldData[$modelName][$id];

            unset($this->oldData[$modelName][$id]);
            if (!$this->oldData[$modelName]) {
                unset($this->oldData[$modelName]);
            }
            if (!$this->oldData) {
                unset($this->oldData);
            }
        }

        $logModel = $model->getOrmManager()->getModel(LogModel::NAME);
        $logModel->logUpdate($modelName, $logFields, $data, $oldData);
    }

    /**
     * Hook before updating a field
     * @param pallo\library\orm\model\Model $model
     * @param integer $id
     * @param string $fieldName
     * @param mixed $value
     * @return null
     */
    public function preUpdateField(Model $model, $id, $fieldName, $value) {
        $modelName = $model->getName();
        $key = $id . '::' . $fieldName;

        if (!isset($this->oldData)) {
            $this->oldData = array($modelName => array());
        } elseif (!isset($this->oldData[$modelName])) {
            $this->oldData[$modelName] = array();
        }

        $query = $model->createQuery();
        $query->setIncludeUnlocalizedData(true);
        $query->setFields('{id}, {' . $fieldName . '}');
        $query->addCondition('{id} = %1%', $id);

        $this->oldData[$modelName][$key] = $query->queryFirst();
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
        $modelName = $model->getName();
        $key = $id . '::' . $fieldName;

        $fields = array($fieldName => null);

        $data = $model->createData(false);
        $data->id = $id;
        $data->$fieldName = $value;

        $oldData = null;
        if (isset($this->oldData[$modelName][$key])) {
            $oldData = $this->oldData[$modelName][$key];

            unset($this->oldData[$modelName][$key]);
            if (!$this->oldData[$modelName]) {
                unset($this->oldData[$modelName]);
            }
            if (!$this->oldData) {
                unset($this->oldData);
            }
        }

        $logModel = $model->getOrmManager()->getModel(LogModel::NAME);
        $logModel->logUpdate($modelName, $fields, $data, $oldData);
    }

    /**
     * Hook before deleting data
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preDelete(Model $model, $data) {
        $logModel = $model->getOrmManager()->getModel(LogModel::NAME);
        $logModel->logDelete($model->getName(), $data);
    }

    /**
     * Gets the fields which need to be logged
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    protected function getLogFields(Model $model, $data) {
        $reflectionHelper = $model->getReflectionHelper();

        $logFields = array();

        $fields = $model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY || $field->isLocalized()) {
                continue;
            }

            $logFields[$fieldName] = $reflectionHelper->getProperty($data, $fieldName);
        }

        return $logFields;
    }

}