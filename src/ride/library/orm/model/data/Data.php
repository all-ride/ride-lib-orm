<?php

namespace ride\library\orm\model\data;

use ride\library\Data as CoreData;

/**
 * Generic data container
 */
class Data implements DatedData, VersionedData {

    /**
     * Id of the log data
     * @var integer
     */
    public $id;

    /**
     * Code of the locale of the data
     * @var string
     */
    public $dataLocale;

    /**
     * Code of the locale of the data
     * @var array
     */
    public $dataLocales;

    /**
     * Timestamp this data was added to the model
     * @var integer
     */
    public $dateAdded;

    /**
     * Timestamp this data was last modified in the model
     * @var integer
     */
    public $dateModified;

    /**
     * Version of the data
     * @var integer
     */
    public $version;

    /**
     * Gets a string representation of this data
     * @return string
     */
    public function __toString() {
        if ($this->id) {
            return 'Data ' . $this->id;
        } else {
            return 'New Data';
        }
    }

    /**
     * Sets the add date
     * @param integer $timestamp UNIX timestamp of the add date
     * @return null
     */
    public function setDateAdded($timestamp = null) {
        if ($this->dateAdded) {
            return;
        }

        if ($timestamp === null) {
            $timestamp = time();
        }

        $this->dateAdded = $timestamp;
    }

    /**
     * Gets the add date
     * @return integer UNIX timestamp of the add date
    */
    public function getDateAdded() {
        return $this->dateAdded;
    }

    /**
     * Sets the modified date
     * @param integer $timestamp UNIX timestamp of the modified date
     * @return null
    */
    public function setDateModified($timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $this->dateModified = $timestamp;
    }

    /**
     * Gets the modified date
     * @return integer UNIX timestamp of the modified date
    */
    public function getDateModified() {
        return $this->dateModified;
    }

    /**
     * Sets the current version of the data
     * @param integer $version
     * @return null
     */
    public function setVersion($version) {
        $this->version = $version;
    }

    /**
     * Gets the current version of the data
     * @return integer
    */
    public function getVersion() {
        return $this->version;
    }

}