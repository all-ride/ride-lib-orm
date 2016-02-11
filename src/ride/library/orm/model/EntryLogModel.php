<?php

namespace ride\library\orm\model;

use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\entry\EntryProxy;
use ride\library\orm\exception\OrmException;
use ride\library\orm\model\behaviour\DateBehaviour;
use ride\library\orm\query\ModelQuery;

/**
 * Model for logging model actions
 */
class EntryLogModel extends GenericModel {

    /**
     * Name of the log model
     * @var string
     */
    const NAME = 'EntryLog';

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

        $this->addBehaviour(new DateBehaviour());
        $this->dataListDepth = 0;
    }

    /**
     * Gets the entry as it was on the provided date
     * @param string $modelName Name of the model
     * @param integer $id Primary key of the entry
     * @param integer $date Timestamp of the date
     * @param integer $recursiveDepth
     * @param string $locale
     * @return mixed Entry
     */
    public function getEntryByDate($modelName, $id, $date, $recursiveDepth = 1, $locale = null) {
        $query = $this->createQuery($locale);
        $query->addCondition('{model} = %1% AND {entry} = %2%', $modelName, $id);
        $query->addCondition('{dateAdded} <= %1%', $date);
        $query->addOrderBy('{version} ASC');

        return $this->getEntryByQuery($modelName, $id, $query, $recursiveDepth);
    }

    /**
     * Gets the entry as it was with the provided version
     * @param string $modelName Name of the model
     * @param integer $id Primary key of the entry
     * @param integer $version Version number of the requested entry
     * @param integer $recursiveDepth
     * @param string $locale
     * @return mixed Entry
     */
    public function getEntryByVersion($modelName, $id, $version, $recursiveDepth = 1, $locale = null) {
        $query = $this->createQuery($locale);
        $query->addCondition('{model} = %1% AND {entry} = %2%', $modelName, $id);
        $query->addCondition('{version} <= %1%', $version);
        $query->addOrderBy('{version} ASC');

        return $this->getEntryByQuery($modelName, $id, $query, $recursiveDepth);
    }

    /**
     * Gets the entry for the logs in the provided query
     * @param string $modelName Name of the model
     * @param integer $id Primary key of the data
     * @param \ride\library\orm\query\ModelQuery $query Query of the entry logs
     * @return mixed Entry
     */
    protected function getEntryByQuery($modelName, $id, ModelQuery $query, $recursiveDepth) {
        $locale = $query->getLocale();

        $entryModel = $this->getModel($modelName);
        $entryMeta = $entryModel->getMeta();

        if (!$entryMeta->getOption('behaviour.log')) {
            // not a logged model, just return the current value
            $query = $entryModel->createQuery($locale);
            $query->addCondition('{id} = %1%', $id);

            return $query->queryFirst();
        }

        $logs = $query->query();
        if (!$logs) {
            throw new OrmException('No logs for ' . $modelName . ' #' . $id);
        }

        $entryDate = 0;
        $models = array();
        $properties = array();

        foreach ($logs as $log) {
            foreach ($log->changes as $change) {
                $properties[$change->getFieldName()] = $change->getNewValue();
            }

            $entryDate = $log->dateAdded;
        }

        foreach ($properties as $fieldName => $value) {
            if (!$entryMeta->hasField($fieldName)) {
                unset($properties[$fieldName]);

                continue;
            }

            $field = $entryMeta->getField($fieldName);

            if ($field instanceof BelongsToField || $field instanceof HasOneField) {
                if ($value && is_numeric($value)) {
                    if ($recursiveDepth) {
                        $fieldModel = $field->getRelationModelName();

                        $properties[$fieldName] = $this->getEntryByDate($fieldModel, $value, $recursiveDepth - 1, $locale);
                    }
                } else {
                    $properties[$fieldName] = null;
                }
            } elseif ($field instanceof HasManyField) {
                $fieldModel = $field->getRelationModelName();

                if (!$value) {
                    $properties[$fieldName] = array();
                } else {
                    $ids = explode(self::VALUE_SEPARATOR, $value);

                    $values = array();
                    foreach ($ids as $id) {
                        $id = trim($id);
                        if (!$id || !is_numeric($id)) {
                            continue;
                        }

                        if ($recursiveDepth) {
                            $values[$id] = $this->getEntryByDate($fieldModel, $id, $entryDate, $recursiveDepth - 1, $locale);
                        } else {
                            $values[$id] = $fieldModel->createProxy($id, $locale);
                        }
                    }

                    $properties[$fieldName] = $values;
                }
            }
        }

        $entry = $entryModel->createProxy($id, $locale, $properties);

        if ($entryMeta->isLocalized()) {
            $localizedModel = $dataMeta->getLocalizedModel();
            $localizedId = $localizedModel->getLocalizedId($id, $locale);

            if ($localizedId) {
                $localizedEntry = $this->getEntryByDate($localizedModel->getName(), $localizedId, $entryDate, $recursiveDepth, $locale);

                $localizedFields = $entryMeta->getLocalizedFields();
                foreach ($localizedFields as $fieldName => $field) {
                    $this->reflectionHelper->setProperty($entry, $fieldName, $this->reflectionHelper->getProperty($localizedEntry, $fieldName));
                }

                $entry->setLocale($locale);
                $entry->setIsLocalized(true);
            } else {
                $entry->setIsLocalized(false);
            }
        }

        return $entry;
    }

    /**
     * Gets the changes of the provided model
     * @param string $modelName Name of the model
     * @param integer $id Primary key of the entry
     * @param integer $version Version of the entry
     * @param string $locale Code of the locale
     * @param integer $since Timestamp of the date
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

        $condition .= ($condition ? ' AND ' : '') . '{model} = %1%';

        if ($id) {
            $condition .= ' AND {entry} = %2%';
        }
        if ($version) {
            $condition .= ' AND {version} = %3%';
        }

        $query->addCondition($condition, $modelName, $id, $version, $since);

        if ($meta->isLocalized()) {
            if ($locale == null) {
                $locale = $this->orm->getLocale();
            }

            $localizedModel = $model->getLocalizedModel();
            $localizedId = null;

            $condition = '{model} = %1%';

            if ($id) {
                $localizedId = $localizedModel->getLocalizedId($id, $locale);
                $condition .= ' AND {entry} = %2%';
            }

            $query->addCondition($condition, $localizedModel->getName(), $localizedId);
        }

        $query->addOrderBy('{dateAdded} DESC');
        $query->addOrderBy('{version} DESC');

        return $query->query();
    }

    /**
     * Gets the log for an entry
     * @param string $modelName Name of the model
     * @param integer $id Primary key of the entry
     * @param integer $version If provided, only the log of this version will be
     * retrieved, else all logs of the entry
     * @param string $locale
     * @return array Array with entry logs
     */
    public function getLog($modelName, $id, $version = null, $locale = null) {
        $logs = $this->getChanges($modelName, $id, $version, $locale);
        $logs = array_reverse($logs);

        $versions = array();
        foreach ($logs as $log) {
            $id = $this->reflectionHelper->getProperty($log, 'dateAdded');
            $changes = $this->reflectionHelper->getProperty($log, 'changes');
            $logModelName = $this->reflectionHelper->getProperty($log, 'changes');

            if (!$changes && $logModelName != $modelName) {
                continue;
            }

            if ($logModelName != $modelName) {
                if (!isset($versions[$id])) {
                    if (isset($versions[$id - 1])) {
                        $versionChanges = $changes + $this->reflectionHelper->getProperty($versions[$id - 1], 'changes');

                        $this->reflectionHelper->setProperty($versions[$id - 1], 'changes', $versionChanges);

                        continue;
                    } else {
                        $versions[$id] = $log;
                    }
                }
            } elseif (!isset($versions[$id])) {
                $versions[$id] = $log;

                continue;
            }

            $versionModelName = $this->reflectionHelper->getProperty($versions[$id], 'model');

            if ($versionModelName != $modelName && $logModelName == $modelName) {
                $this->reflectionHelper->setProperty($versions[$id], 'model', $modelName);
                $this->reflectionHelper->setProperty($versions[$id], 'entry', $this->reflectionHelper->getProperty($log, 'entry'));
                $this->reflectionHelper->setProperty($versions[$id], 'version', $this->reflectionHelper->getProperty($log, 'version'));
            }

            $versionChanges = $changes + $this->reflectionHelper->getProperty($versions[$id], 'changes');

            $this->reflectionHelper->setProperty($versions[$id], 'changes', $versionChanges);
        }

        return array_reverse($versions);
    }

    /**
     * Logs an insert action for the provided entry
     * @param Model $model Instance of the model of the entry
     * @param mixed $entry New entry to log
     * @return null
     */
    public function logInsert(Model $model, $entry) {
        $changeModel = $this->getModel(EntryLogChangeModel::NAME);
        $changes = array();

        $fields = $this->getLogFields($model, $entry, false);
        foreach ($fields as $fieldName => $value) {
            $change = $changeModel->createEntry();
            $change->setFieldName($fieldName);
            $change->setNewValue($value);

            $changes[] = $change;
        }

        $log = $this->createEntryLog($model->getName(), $entry, true);
        $log->setAction(self::ACTION_INSERT);
        $log->setChanges($changes);

        $this->save($log);
    }

    /**
     * Retrieves the log fields of the entry before update
     * @return array Array with the field name as key and the log value as value
     */
    public function prepareLogUpdate(Model $model, $entry) {
        return $this->getLogFields($model, $entry, true);
    }

    /**
     * Logs an update action for the provided entry
     * @param Model $model Instance of the model of the entry
     * @param mixed $entry Updated entry to log
     * @param array $preUpdateFields Array with the potential field before
     * update, basicly the output of prepareLogUpdate
     * @return null
     * @see prepareLogUpdate
     */
    public function logUpdate(Model $model, $entry, array $preUpdateFields) {
        $changeModel = $this->getModel(EntryLogChangeModel::NAME);
        $changes = array();

        $fields = $this->getLogFields($model, $entry, false);
        foreach ($fields as $fieldName => $value) {
            $isOldValueSet = array_key_exists($fieldName, $preUpdateFields);
            if ($isOldValueSet && $preUpdateFields[$fieldName] == $value) {
                continue;
            } elseif (!$isOldValueSet) {
                $oldValue = null;
            } else {
                $oldValue = $preUpdateFields[$fieldName];
            }

            $change = $changeModel->createEntry();
            $change->setFieldName($fieldName);
            $change->setOldValue($oldValue);
            $change->setNewValue($value);

            $changes[] = $change;
        }

        if (!$changes) {
            return;
        }

        $log = $this->createEntryLog($model->getName(), $entry, false);
        $log->setAction(self::ACTION_UPDATE);
        $log->setChanges($changes);

        $this->save($log);
    }

    /**
     * Logs a delete action for the provided entry
     * @param Model $model Instance of the model of the entry
     * @param mixed $entry Deleted entry to log
     * @return null
     */
    public function logDelete(Model $model, $entry) {
        $log = $this->createEntryLog($model->getName(), $entry);
        $log->setAction(self::ACTION_DELETE);

        $this->save($log);
    }

    /**
     * Gets the fields which need to be logged
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    private function getLogFields(Model $model, $entry, $loadedFields = false) {
        $isProxy = $entry instanceof EntryProxy;
        $logFields = array();

        $fields = $model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY || $field->isLocalized() || ($isProxy && !$entry->isFieldSet($fieldName))) {
                continue;
            }

            if ($loadedFields) {
                if ($isProxy) {
                    $value = $entry->getLoadedValues($fieldName);
                } else {
                    $value = null;
                }
            } else {
                $value = $this->reflectionHelper->getProperty($entry, $fieldName);
            }

            $logFields[$fieldName] = $this->getLogValue($value);
        }

        return $logFields;
    }

    /**
     * Creates a log value from a model value.
     *
     * Related data objects will be replaced by their id's. A hasMany relation
     * will be translated into a string with the related object id's separated
     * by the VALUE_SEPARATOR constant.
     * @param mixed $value
     * @return string Provided value in a string format
     */
    private function getLogValue($value) {
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
            $logValues[] = $this->getLogValue($v);
        }

        return implode(self::VALUE_SEPARATOR, $logValues);
    }

    /**
     * Creates a log entry based on the provided entry
     * @param string $modelName Name of the model
     * @param mixed $entry Entry
     * @param boolean $isNew Flag to see if this is a new entry
     * @return mixed Log entry
     */
    private function createEntryLog($modelName, $entry, $isNew = false) {
        $id = $this->reflectionHelper->getProperty($entry, ModelTable::PRIMARY_KEY);
        $version = $this->reflectionHelper->getProperty($entry, 'version');

        if ($version !== null) {
            if ($isNew) {
                $version = 1;
            } else {
                $version = $this->getNewVersionForEntry($modelName, $id);
            }
        }

        $log = $this->createEntry();
        $log->setModel($modelName);
        $log->setEntry($id);
        $log->setVersion($version);
        $log->setUser($this->orm->getUserName());

        return $log;
    }

    /**
     * Gets a new version number for a entry
     * @param string $modelName Name of the entry model
     * @param integer $id Primary key of the entry
     * @return integer New version number for the entry
     */
    private function getNewVersionForEntry($modelName, $id) {
        $query = $this->createQuery();
        $query->setFields('{version}');
        $query->addCondition('{model} = %1% AND {entry} = %2%', $modelName, $id);
        $query->addOrderBy('{version} DESC');

        $lastLog = $query->queryFirst();
        if (!$lastLog) {
            return 1;
        }

        return $lastLog->getVersion() + 1;
    }

}
