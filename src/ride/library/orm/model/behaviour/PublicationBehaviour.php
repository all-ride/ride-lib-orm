<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\entry\PublicatedEntry;
use ride\library\orm\model\Model;

/**
 * Interface to add extra behaviour to a model
 */
class PublicationBehaviour extends AbstractBehaviour {

    /**
     * Hook after creating a data container
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postCreateEntry(Model $model, $entry) {
        if (!$entry instanceof PublicatedEntry || $entry->getDatePublished()) {
            return;
        }

        $time = time();

        $entry->setDatePublished($time);
    }

    /**
     * Hook before inserting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preInsert(Model $model, $entry) {
        if (!$entry instanceof PublicatedEntry || $entry->getDatePublished()) {
            return;
        }

        $time = time();

        $entry->setDatePublished($time);
    }
    
}
