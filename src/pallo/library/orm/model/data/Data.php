<?php

namespace pallo\library\orm\model\data;

use pallo\library\Data as CoreData;

/**
 * Generic data container
 */
class Data {

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
     * Version of the data
     * @var integer
     */
    public $version;

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

}