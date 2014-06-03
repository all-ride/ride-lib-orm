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
    private $oldEntry;

    /**
     * Hook after inserting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postInsert(Model $model, $entry) {
        $logFields = $this->getLogFields($model, $entry);

        $logModel = $model->getOrmManager()->getModel(EntryLogModel::NAME);
        $logModel->logInsert($model->getName(), $logFields, $entry);
    }

    /**
     * Hook before updating data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preUpdate(Model $model, $entry) {
        $localeField = LocalizedModel::FIELD_LOCALE;

        $locale = null;
        if (isset($entry->$localeField)) {
            $locale = $entry->$localeField;
        }

        $modelName = $model->getName();

        if (!isset($this->oldEntry)) {
            $this->oldEntry = array($modelName => array());
        } elseif (!isset($this->oldEntry[$modelName])) {
            $this->oldEntry[$modelName] = array();
        }

        $id = $entry->getId();

        $query = $model->createQuery($locale);
        $query->setIncludeUnlocalized(true);
        $query->addCondition('{id} = %1%', $id);

        $this->oldEntry[$modelName][$id] = $query->queryFirst();
    }

    /**
     * Hook after updating data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postUpdate(Model $model, $entry) {
        $logFields = $this->getLogFields($model, $entry);

        $modelName = $model->getName();
        $id = $entry->getId();

        $oldEntry = null;
        if (isset($this->oldEntry[$modelName][$id])) {
            $oldEntry = $this->oldEntry[$modelName][$id];

            unset($this->oldEntry[$modelName][$id]);
            if (!$this->oldEntry[$modelName]) {
                unset($this->oldEntry[$modelName]);
            }
            if (!$this->oldEntry) {
                unset($this->oldEntry);
            }
        }

        $logModel = $model->getOrmManager()->getModel(EntryLogModel::NAME);
        $logModel->logUpdate($modelName, $logFields, $entry, $oldEntry);
    }

    /**
     * Hook before deleting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preDelete(Model $model, $entry) {
        $logModel = $model->getOrmManager()->getModel(EntryLogModel::NAME);
        $logModel->logDelete($model->getName(), $entry);
    }

    /**
     * Gets the fields which need to be logged
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    protected function getLogFields(Model $model, $entry) {
        $reflectionHelper = $model->getReflectionHelper();

        $logFields = array();

        $fields = $model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY || $field->isLocalized()) {
                continue;
            }

            $logFields[$fieldName] = $reflectionHelper->getProperty($entry, $fieldName);
        }

        return $logFields;
    }

}
