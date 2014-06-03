<?php

namespace ride\library\orm\entry;

/**
 * Interface for a versioned entry
 */
interface VersionedEntry {

    /**
     * Sets the current version of the entry
     * @param integer $version
     * @return null
     */
    public function setVersion($version);

    /**
     * Gets the current version of the entry
     * @return integer
     */
    public function getVersion();

}
