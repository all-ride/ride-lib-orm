<?php

namespace ride\library\orm\entry;

/**
 * Interface for a dated entry
 */
interface PublishedEntry {

    /**
     * Sets the publication date
     * @param integer $timestamp UNIX timestamp of the publication date
     * @return null
     */
    public function setDatePublished($timestamp);

    /**
     * Gets the publication date
     * @return integer UNIX timestamp of the publication date
     */
    public function getDatePublished();

    /**
     * Sets the isPublished flag
     * @param boolean
     * @return null
     */
    public function setIsPublished($isPublished);

    /**
     * Gets the isPublished flag
     * @return boolean
     */
    public function isPublished();

}
