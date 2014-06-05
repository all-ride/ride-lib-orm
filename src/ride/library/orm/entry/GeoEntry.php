<?php

namespace ride\library\orm\entry;

/**
 * Interface for an entry ith geo location support
 */
interface GeoEntry {

    /**
     * Sets the latitude coordinate
     * @param float $latitude
     * @return null
     */
    public function setLatitude($latitude);

    /**
     * Gets the longitude coordinate
     * @return float|null
     */
    public function getLatitude();

    /**
     * Sets the longitude coordinate
     * @param float $longitude
     * @return null
     */
    public function setLongitude($longitude);

    /**
     * Gets the longitude coordinate
     * @return float|null
     */
    public function getLongitude();

    /**
     * Gets the address to lookup the coordinates for
     * @return string
     */
    public function getGeoAddress();

}
