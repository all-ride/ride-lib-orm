<?php

namespace ride\library\orm\entry;

/**
 * Interface for a localized entry
 */
interface LocalizedEntry {

    /**
     * Sets the locale of the entry
     * @param string $version
     * @return null
     */
    public function setLocale($locale);

    /**
     * Gets the locale of the entry
     * @return string
     */
    public function getLocale();

    /**
     * Sets whether this entry is localized in the requested locale
     * @param boolean $isLocalized
     * @return null
     */
    public function setIsLocalized($isLocalized);

    /**
     * Gets whether this entry is localized in the requested locale
     * @return boolean
     */
    public function isLocalized();

}
