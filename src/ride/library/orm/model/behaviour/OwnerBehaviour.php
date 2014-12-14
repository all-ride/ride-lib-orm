<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\entry\OwnedEntry;
use ride\library\orm\model\Model;

/**
 * Behaviour to keep the owner of a entry
 */
class OwnerBehaviour extends AbstractBehaviour {

    /**
     * Gets the owner for this behavious
     * @param \ride\library\orm\model\Model $model
     * @return string
     */
    protected function getOwner(Model $model) {
        return $model->getOrmManager()->getUserName();
    }

    /**
     * Hook after creating a data container
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postCreateEntry(Model $model, $entry) {
        if (!$entry instanceof OwnedEntry || $entry->getOwner()) {
            return;
        }

        $owner = $this->getOwner($model);

        $entry->setOwner($owner);
    }

    /**
     * Hook before inserting an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preInsert(Model $model, $entry) {
        if (!$entry instanceof OwnedEntry || $entry->getOwner()) {
            return;
        }

        $entry->setOwner($this->getOwner($model));
    }

}
