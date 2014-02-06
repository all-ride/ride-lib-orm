<?php

namespace pallo\library\orm\model\data;

/**
 * Interface for versioned data
 */
interface VersionedData {

    /**
     * Sets the current version of the data
     * @param integer $version
     * @return null
     */
    public function setVersion($version);

    /**
     * Gets the current version of the data
     * @return integer
     */
    public function getVersion();

}