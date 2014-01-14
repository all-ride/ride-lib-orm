<?php

namespace pallo\library\orm\model\data;

/**
 * A collection of data with the total
 */
class DataCollection {

    /**
     * Array of data objects
     * @var array
     */
    protected $data;

    /**
     * Total number of data objects
     * @var integer
     */
    protected $total;

    /**
     * Constructs a new data collection
     * @param array $data Array of data objects
     * @param integer $total Total number of available objects
     * @return null
     */
    public function __construct(array $data, $total) {
        $this->data = $data;
        $this->total = $total;
    }

    /**
     * Gets the data
     * @return array Array of data objects
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Gets the total number of available objects
     * @return number Total number
     */
    public function getTotal() {
        return $this->total;
    }

}