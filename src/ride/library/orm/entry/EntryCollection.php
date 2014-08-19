<?php

namespace ride\library\orm\entry;

use \ArrayAccess;
use \Iterator;

/**
 * A collection of entries with the total number of available entries
 */
class EntryCollection implements Iterator, ArrayAccess {

    /**
     * Array of entries
     * @var array
     */
    protected $entries;

    /**
     * Total number of entries
     * @var integer
     */
    protected $total;

    /**
     * Constructs a new entry collection
     * @param array $entries Array of entries
     * @param integer $total Total number of available entries
     * @return null
     */
    public function __construct(array $entries, $total) {
        $this->entries = $entries;
        $this->total = $total;
    }

    /**
     * Gets the entries
     * @return array Array of entries
     */
    public function getEntries() {
        return $this->entries;
    }

    /**
     * Gets the total number of available entries
     * @return integer Total number
     */
    public function getTotal() {
        return $this->total;
    }

    /**
     * Iterator implementation: resets the internal row pointer
     * @return null
     */
    public function rewind() {
        return reset($this->entries);
    }

    /**
     * Iterator implementation: gets the current row
     * @return array Array with the columns as key and the column values as
     * value
     */
    public function current() {
        return current($this->entries);
    }

    /**
     * Iterator implementation: gets the internal row pointer
     * @return integer Pointer to the current row
     */
    public function key() {
        return key($this->entries);
    }

    /**
     * Iterator implementation: increases the internal row pointer to the next
     * row
     * @return null
     */
    public function next() {
        next($this->entries);
    }

    /**
     * Iterator implementation: checks whether the internal row pointer is on
     * a valid row
     * @return boolean True if the internal row pointer is on a valid row,
     * false otherwise
     */
    public function valid() {
        return isset($this->entries[$this->key()]);
    }

    /**
     * The offsetGet purpose
     * @param string $index
     * @return mixed
     */
    public function offsetGet($index) {
        return isset($this->entries[$index]) ? $this->entries[$index] : null;
    }

    /**
     * The offsetSet purpose
     * @param string $index
     * @param mixed $entry
     * @return null
     */
    public function offsetSet($index, $entry) {
        $this->entries[$index] = $entry;
    }

    /**
     * The offsetUnset purpose
     * @link http://www.php.net/manual/en/cachingiterator.offsetunset.php
     * @param index string <p>
     * The index of the element to be unset.
     * </p>
     * @return void
     */
    public function offsetUnset($index) {
        if (isset($this->entries[$index])) {
            unset($this->entries[$index]);
        }
    }

    /**
     * The offsetExists purpose
     * @link http://www.php.net/manual/en/cachingiterator.offsetexists.php
     * @param index string <p>
     * The index being checked.
     * </p>
     * @return void true if an entry referenced by the offset exists, false otherwise.
     */
    public function offsetExists($index) {
        return isset($this->entries[$index]);
    }

}
