<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\definition\ModelTable;
use ride\library\orm\entry\DatedEntry;
use ride\library\orm\model\Model;

/**
 * Interface to add extra behaviour to a model
 */
class DateBehaviour extends AbstractBehaviour {

    /**
     * Hook after creating a data container
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postCreateEntry(Model $model, $entry) {
        if (!$entry instanceof DatedEntry || $entry->getDateAdded()) {
            return;
        }

        $time = time();

        $entry->setDateAdded($time);
        $entry->setDateModified($time);
    }

    /**
     * Hook before inserting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preInsert(Model $model, $entry) {
        if (!$entry instanceof DatedEntry || $entry->getDateAdded()) {
            return;
        }

        $time = time();

        $entry->setDateAdded($time);
        $entry->setDateModified($time);
    }

    /**
     * Hook before updating data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preUpdate(Model $model, $entry) {
        if (!$entry instanceof DatedEntry) {
            return;
        }

        $entry->setDateModified(time());
    }

}
