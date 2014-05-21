<?php

namespace ride\library\orm\model;

use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\exception\OrmException;
use ride\library\orm\model\behaviour\DatedBehaviour;
use ride\library\orm\query\ModelQuery;


/**
 * Model for logging model actions
 */
class LogModel extends GenericModel {

    /**
     * Name of the log model
     * @var string
     */
    const NAME = 'ModelLog';

    /**
     * Name of the insert action
     * @var string
     */
    const ACTION_INSERT = 'insert';

    /**
     * Name of the update action
     * @var string
     */
    const ACTION_UPDATE = 'update';

    /**
     * Name of the delete action
     * @var string
     */
    const ACTION_DELETE = 'delete';

    /**
     * Separator between log values
     * @var string
     */
    const VALUE_SEPARATOR = ', ';

    /**
     * Hook to perform extra initialization when constructing the model
     * @return null
     */
    protected function initialize() {
        parent::initialize();

        $this->addBehaviour(new DatedBehaviour());
        $this->dataListDepth = 0;
    }

    /**
     * Gets the data object as it was on the provided date
     * @param string $modelName Name of the data model
     * @param int $id Primary key of the data
     * @param int $date Timestamp of the date
     * @return mixed Data
     */
    public function getDataByDate($modelName, $id, $date, $recursiveDepth = 1, $locale = null) {
        $query = $this->createQuery($locale);
        $query->addCondition('{dataModel} = %1% AND {dataId} = %2%', $modelName, $id);
        $query->addCondition('{dateAdded} <= %1%', $date);
        $query->addOrderBy('{dataVersion} ASC');

        return $this->getDataByQuery($modelName, $id, $query, $recursiveDepth);
    }

    /**
     * Gets the data object as it was at the provided version
     * @param string $modelName Name of the data model
     * @param int $id Primary key of the data
     * @param int $version Previous version of the data
     * @return mixed Data
     */
    public function getDataByVersion($modelName, $id, $version, $recursiveDepth = 1, $locale = null) {
        $query = $this->createQuery($locale);
        $query->addCondition('{dataModel} = %1% AND {dataId} = %2%', $modelName, $id);
        $query->addCondition('{dataVersion} <= %1%', $version);
        $query->addOrderBy('{dataVersion} ASC');

        return $this->getDataByQuery($modelName, $id, $query, $recursiveDepth);
    }

    /**
     * Gets the data object for the logs in the provided query
     * @param string $modelName Name of the data model
     * @param int $id Primary key of the data
     * @param \ride\library\orm\query\ModelQuery $query of the data logs
     * @return mixed Data
     * @todo finish
     */
    protected function getDataByQuery($modelName, $id, ModelQuery $query, $recursiveDepth) {
        $dataModel = $this->getModel($modelName);
        $dataMeta = $dataModel->getMeta();

        if (!$dataMeta->getOption('behaviour.log')) {
            $query = $dataModel->createQuery();
            $query->addCondition('{id} = %1%', $id);

            return $query->queryFirst();
        }

        $logs = $query->query();
        if (!$logs) {
            throw new OrmException('No logs for ' . $modelName . ' ' . $id);
        }

        $dataDate = 0;
        $models = array();
        $properties = array(
            ModelTable::PRIMARY_KEY => $id
        );

        foreach ($logs as $log) {
            foreach ($log->changes as $change) {
                $properties[$change->fieldName] = $change->newValue;
            }

            $dataDate = $log->dateAdded;
        }

        foreach ($properties as $fieldName => $value) {
            if (!$dataMeta->hasField($fieldName)) {
                unset($properties[$fieldName]);

                continue;
            }
        }

        $data = $dataModel->createData($properties);

        foreach ($properties as $fieldName => $value) {
            $field = $dataMeta->getField($fieldName);

            if ($field instanceof BelongsToField) {
                if ($value && is_numeric($value)) {
                    if ($recursiveDepth) {
                        $fieldModel = $field->getRelationModelName();

                        $data->$fieldName = $this->getDataByDate($fieldModel, $value, $dataDate, $recursiveDepth - 1);
                    }
                } else {
                    $data->$fieldName = null;
                }
            } elseif ($field instanceof HasManyField) {
                $fieldModel = $field->getRelationModelName();

                if (!$value) {
                    $data->$fieldName = array();
                } else {
                    $ids = explode(self::VALUE_SEPARATOR, $value);

                    $values = array();
                    foreach ($ids as $id) {
                        $id = trim($id);
                        if (!$id || !is_numeric($id)) {
                            continue;
                        }

                        if ($recursiveDepth) {
                            $values[$id] = $this->getDataByDate($fieldModel, $id, $dataDate, $recursiveDepth - 1);
                        } else {
                            $values[$id] = $id;
                        }
                    }

                    $data->$fieldName = $values;
                }
            }
        }

        if ($dataMeta->isLocalized()) {
            $locale = $query->getLocale();

            $localizedModel = $dataMeta->getLocalizedModel();
            $localizedId = $localizedModel->getLocalizedId($data->id, $locale);

            if ($localizedId) {
                $localizedData = $this->getDataByDate($localizedModel->getName(), $localizedId, $dataDate, $recursiveDepth);

                $localizedFields = $dataMeta->getLocalizedFields();
                foreach ($localizedFields as $fieldName => $field) {
                    if (isset($localizedData->$fieldName)) {
                        $data->$fieldName = $localizedData->$fieldName;
                    }
                }

                $data->dataLocale = $locale;
            }
        }

        return $data;
    }

    /**
     * Gets the changes of the provided model
     * @param string $modelName Name of the data model
     * @param int $id Primary key of the data
     * @param int $version
     * @param string $locale
     * @param int $since
     * @return array
     */
    public function getChanges($modelName, $id = null, $version = null, $locale = null, $since = null) {
        $model = $this->getModel($modelName);
        $meta = $model->getMeta();

        $query = $this->createQuery();
        $query->setOperator('OR');

        $condition = '';

        if ($since) {
            $condition .= '{dateAdded} >= %4%';
        }

        $condition .= ($condition ? ' AND ' : '') . '{dataModel} = %1%';

        if ($id) {
            $condition .= ' AND {dataId} = %2%';
        }
        if ($version) {
            $condition .= ' AND {dataVersion} = %3%';
        }

        $query->addCondition($condition, $modelName, $id, $version, $since);

        if ($meta->isLocalized()) {
            if ($locale == null) {
                $locale = $this->orm->getLocale();
            }

            $localizedModel = $meta->getLocalizedModel();
            $localizedId = null;

            $condition = '{dataModel} = %1%';

            if ($id) {
                $localizedId = $localizedModel->getLocalizedId($id, $locale);
                $condition .= ' AND {dataId} = %2%';
            }

            $query->addCondition($condition, $localizedModel->getName(), $localizedId);
        }

        $query->addOrderBy('{dateAdded} DESC');
        $query->addOrderBy('{dataVersion} DESC');

        return $query->query();
    }

    /**
     * Gets the log for a data object
     * @param string $modelName Name of the data model
     * @param integer $id Primary key of the data
     * @param integer $version If provided, the log of this version will be retrieved, else all logs
     * @param string $locale
     * @return array Array with LogData objects
     */
    public function getLog($modelName, $id, $version = null, $locale = null) {
        $logs = $this->getChanges($modelName, $id, $version, $locale);
        $logs = array_reverse($logs);

        $versions = array();
        foreach ($logs as $log) {
            $id = $log->dateAdded;

            if (!$log->changes && $log->dataModel != $modelName) {
                continue;
            }

            if ($log->dataModel != $modelName) {
                if (!isset($versions[$id])) {
                    if (isset($versions[$id - 1])) {
                        $versions[$id - 1]->changes = $log->changes + $versions[$id - 1]->changes;

                        continue;
                    } else {
                        $versions[$id] = $log;
                    }
                }
            } elseif (!isset($versions[$id])) {
                $versions[$id] = $log;

                continue;
            }

            if ($versions[$id]->dataModel != $modelName && $log->dataModel == $modelName) {
                $versions[$id]->dataModel = $modelName;
                $versions[$id]->dataId = $log->dataId;
                $versions[$id]->dataVersion = $log->dataVersion;
            }

            $versions[$id]->changes = $log->changes + $versions[$id]->changes;
        }

        foreach ($versions as $id => $version) {
            $changes = $version->changes;
            foreach ($changes as $index => $change) {
                if ($change->fieldName == VersionField::NAME || $change->fieldName == LocalizedModel::FIELD_LOCALE || $change->fieldName == LocalizedModel::FIELD_DATA) {
                    unset($changes[$index]);
                }
            }

            $version->changes = $changes;

            $versions[$id] = $version;
        }

        return array_reverse($versions);
    }

    /**
     * Logs a insert action for the provided data
     * @param string $modelName Name of the data model
     * @param array $fields Array with the field name as key
     * @param mixed $data New data object
     * @return null
     */
    public function logInsert($modelName, array $fields, $data) {
        $log = $this->createLog($modelName, $data, true);
        $log->action = self::ACTION_INSERT;

        $logChangeModel = $this->getModel(LogChangeModel::NAME);

        foreach ($fields as $fieldName => $null) {
            $change = $logChangeModel->createData();
            $change->fieldName = $fieldName;
            $change->newValue = $this->createLogValue($this->reflectionHelper->getProperty($data, $fieldName));

            $log->changes[] = $change;
        }

        $this->save($log);
    }

    /**
     * Logs a update action for the provided data
     * @param string $modelName Name of the data model
     * @param array $fields Array with the field name as key
     * @param mixed $data Updated data object
     * @param array $oldData Current data object from the model
     * @return null
     */
    public function logUpdate($modelName, array $fields, $data, $oldData) {
        $log = $this->createLog($modelName, $data);
        $log->action = self::ACTION_UPDATE;

        $logChangeModel = $this->getModel(LogChangeModel::NAME);

        foreach ($fields as $fieldName => $null) {
            $oldValue = $this->createLogValue($this->reflectionHelper->getProperty($oldData, $fieldName));
            $newValue = $this->createLogValue($this->reflectionHelper->getProperty($data, $fieldName));

            if ($oldValue == $newValue || ($oldValue === null && $newValue === null)) {
                continue;
            }

            $change = $logChangeModel->createData();
            $change->fieldName = $fieldName;
            $change->oldValue = $oldValue;
            $change->newValue = $newValue;

            $log->changes[] = $change;
        }

        $this->save($log);
    }

    /**
     * Logs a delete action for the provided data
     * @param string $modelName Name of the data model
     * @param mixed $data Data object
     * @return null
     */
    public function logDelete($modelName, $data) {
        $log = $this->createLog($modelName, $data);
        $log->action = self::ACTION_DELETE;

        $this->save($log);
    }

    /**
     * Creates a log data object based on the provided data
     * @param string $modelName Name of the data model
     * @param mixed $data Data object
     * @param boolean $isNew Flag to see if this is a new data object
     * @return mixed Log data object
     */
    private function createLog($modelName, $data, $isNew = false) {
        $id = $this->reflectionHelper->getProperty($data, ModelTable::PRIMARY_KEY);

        $log = $this->createData();
        $log->dataModel = $modelName;
        $log->dataId = $id;

        if (isset($data->version)) {
            $log->dataVersion = $data->version;
        } elseif ($isNew) {
            $log->dataVersion = 1;
        } else {
            $log->dataVersion = $this->getNewVersionForData($modelName, $id);
        }

        $log->user = $this->getUser();

        return $log;
    }

    /**
     * Gets the name of the current user
     * @return string|null
     */
    protected function getUser() {
        return null;
    }

    /**
     * Creates a log value from a model value.
     *
     * Related data objects will be replaced by their id's. A hasMany relation will be translated into a
     * string with the related object id's separated by the VALUE_SEPARATOR constant.
     * @param mixed $value
     * @return string Provided value in a string format
     */
    private function createLogValue($value) {
        if (is_null($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if (is_object($value)) {
            return $this->reflectionHelper->getProperty($value, ModelTable::PRIMARY_KEY);
        }

        $logValues = array();
        foreach ($value as $v) {
            $logValues[] = $this->createLogValue($v);
        }

        sort($logValues);

        return implode(self::VALUE_SEPARATOR, $logValues);
    }

    /**
     * Gets a new version for a data object
     * @param string $modelName Name of the data model
     * @param int $id Primary key of the data
     * @return int New version for the data object
     */
    private function getNewVersionForData($modelName, $id) {
        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{dataVersion}');
        $query->addCondition('{dataModel} = %1% AND {dataId} = %2%', $modelName, $id);
        $query->addOrderBy('{dataVersion} DESC');

        $lastLog = $query->queryFirst();

        if (!$lastLog) {
            return 1;
        }

        return $lastLog->dataVersion + 1;
    }

}
