<?php

namespace ride\library\orm\entry;

/**
 * Interface for a slugged entry
 */
interface SluggedEntry {

    /**
     * Sets the slug of the entry
     * @param string $slug
     * @return null
     */
    public function setSlug($slug);

    /**
     * Gets the slug of the entry
     * @return integer
     */
    public function getSlug();

    /**
     * Gets the desired slug based on properties of the entry
     * @return string
     */
    public function getSlugBase();

}
