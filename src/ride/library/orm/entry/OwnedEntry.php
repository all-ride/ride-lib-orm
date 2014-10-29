<?php

namespace ride\library\orm\entry;

/**
 * Interface for a owned entry
 */
interface OwnedEntry {

    // /**
    // * Sets the owner of the entry
    // * @param integer $version
    // * @return null
    // */
    // conflicts when owner should be a relation instead of username
    // public function setOwner($owner);

    /**
     * Gets the current version of the entry
     * @return integer
     */
    public function getOwner();

}
