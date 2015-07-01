<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\LocalizedModel;
use ride\library\orm\model\EntryLogModel;
use ride\library\orm\model\Model;

/**
 * Behaviour to keep a log of your manipulation actions
 */
class LogBehaviour extends AbstractBehaviour {

    /**
     * Data container of the pre save entry states
     * @var array
     */
    private $preUpdateFields;

    /**
     * Hook after inserting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postInsert(Model $model, $entry) {
        $logModel = $model->getOrmManager()->getModel(EntryLogModel::NAME);
        $logModel->logInsert($model, $entry);
    }

    /**
     * Hook before updating data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preUpdate(Model $model, $entry) {
        $logModel = $model->getOrmManager()->getModel(EntryLogModel::NAME);

        $this->preUpdateFields[$model->getName()][$entry->getId()] = $logModel->prepareLogUpdate($model, $entry);
    }

    /**
     * Hook after updating data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postUpdate(Model $model, $entry) {
        $modelName = $model->getName();
        $id = $entry->getId();

        if (isset($this->preUpdateFields[$modelName][$id])) {
            $preUpdateFields = $this->preUpdateFields[$modelName][$id];

            unset($this->preUpdateFields[$modelName][$id]);
            if (!$this->preUpdateFields[$modelName]) {
                unset($this->preUpdateFields[$modelName]);
            }
            if (!$this->preUpdateFields) {
                unset($this->preUpdateFields);
            }
        } else {
            $preUpdateFields = null;
        }

        $logModel = $model->getOrmManager()->getModel(EntryLogModel::NAME);
        $logModel->logUpdate($model, $entry, $preUpdateFields);
    }

    /**
     * Hook before deleting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preDelete(Model $model, $entry) {
        $logModel = $model->getOrmManager()->getModel(EntryLogModel::NAME);
        $logModel->logDelete($model, $entry);
    }

}
