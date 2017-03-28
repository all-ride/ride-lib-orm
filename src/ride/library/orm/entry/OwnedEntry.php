<?php

namespace ride\library\orm\entry;

/**
 * Interface for a owned entry
 */
interface OwnedEntry {

    /**
     * Sets the owner of the entry
     * @param string $owner
     * @return null
     */
    public function setOwner($owner = null);

    /**
     * Gets the owner of the entry
     * @return string
     */
    public function getOwner();

}
