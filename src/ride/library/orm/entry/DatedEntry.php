<?php

namespace ride\library\orm\entry;

/**
 * Interface for a dated entry
 */
interface DatedEntry {

    /**
     * Sets the add date
     * @param integer $timestamp UNIX timestamp of the add date
     * @return null
     */
    public function setDateAdded($timestamp);

    /**
     * Gets the add date
     * @return integer UNIX timestamp of the add date
     */
    public function getDateAdded();

    /**
     * Sets the modified date
     * @param integer $timestamp UNIX timestamp of the modified date
     * @return null
     */
    public function setDateModified($timestamp);

    /**
     * Gets the modified date
     * @return integer UNIX timestamp of the modified date
     */
    public function getDateModified();

}
