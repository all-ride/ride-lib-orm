<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\definition\ModelTable;
use ride\library\orm\entry\Entry;
use ride\library\orm\entry\VersionedEntry;
use ride\library\orm\model\Model;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\ValidationError;

/**
 * Behaviour to keep a version index in your entry
 */
class VersionBehaviour extends AbstractBehaviour {

    /**
     * Hook after creating a data container
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postCreateEntry(Model $model, $entry) {
        if (!$entry instanceof VersionedEntry || $entry->getVersion()) {
            return;
        }

        $entry->setVersion(0);
    }

    /**
     * Hook before validation of an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function postValidate(Model $model, $entry, ValidationException $exception) {
        if (!$entry instanceof VersionedEntry || $entry->getEntryState() != Entry::STATE_DIRTY) {
            return;
        }

        $currentVersion = $this->findVersionById($model, $entry->getId());
        if ($entry->getVersion() == $currentVersion) {
            return;
        }

        $error = new ValidationError(
            'error.validation.version',
            'Your data is outdated. You are trying to save version %yourVersion% over version %currentVersion%. Try updating your data first.',
            array('yourVersion' => $entry->getVersion(), 'currentVersion' => $currentVersion)
        );

        $exception->addErrors('version', array($error));
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

        $entry = $query->queryFirst();

        if (!$entry) {
            return 0;
        }

        return $entry->getVersion();
    }

    /**
     * Hook before inserting an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preInsert(Model $model, $entry) {
        $this->handleEntry($entry);
    }

    /**
     * Hook before inserting an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preUpdate(Model $model, $entry) {
        $this->handleEntry($entry);
    }

    /**
     * Updates the entry to a new version
     * @param mixed $entry
     * @return null
     */
    private function handleEntry($entry) {
        if (!$entry instanceof VersionedEntry) {
            return;
        }

        $entry->setVersion($entry->getVersion() + 1);
    }

}
