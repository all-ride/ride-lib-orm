<?php

namespace ride\library\orm\entry;

/**
 * Interface for an entry of a model
 */
interface Entry {

    /**
     * State when the entry is new and unsaved
     * @var integer
     */
    const STATE_NEW = 1;

    /**
     * State when the entry has unsaved changes
     * @var integer
     */
    const STATE_DIRTY = 2;

    /**
     * State when the entry has no changes and synced with the data source
     * @var integer
     */
    const STATE_CLEAN = 4;

    /**
     * State when the entry has been deleted
     * @var integer
     */
    const STATE_DELETED = 8;

    /**
     * Gets the state of the entry
     * @return integer State of the entry
     */
    public function getEntryState();

    /**
     * Sets the state of the entry
     * @return integer State of the entry
     */
    public function setEntryState($entryState);

    /**
     * Gets the id of this entry
     * @return integer|string
     */
    public function getId();

    /**
     * Sets the id of this entry
     * @param integer|string $id Id of the entry
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the id is already
     * set
     */
    public function setId($id);

}
