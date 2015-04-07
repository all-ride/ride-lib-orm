<?php

namespace ride\library\orm\entry;

use ride\library\orm\exception\OrmException;

/**
 * Abstract implementation of an entry
 */
abstract class AbstractEntry implements Entry {

    /**
     * State of the entry
     * @var integer
     */
    protected $entryState = self::STATE_NEW;

    /**
     * Id of the entry
     * @var integer|string
     */
    protected $id = 0;

    /**
     * Gets the state of the entry
     * @return integer State of the entry
     */
    public function getEntryState() {
        return $this->entryState;
    }

    /**
     * Sets the state of the entry
     * @return integer State of the entry
     */
    public function setEntryState($entryState) {
        switch ($entryState) {
            case self::STATE_NEW:
            case self::STATE_DIRTY:
            case self::STATE_CLEAN:
            case self::STATE_DELETED:
                $this->entryState = $entryState;

                break;
            default:
                throw new OrmException('Could not set the state of the entry: invalid state provided');
        }
    }

    /**
     * Gets the id of this entry
     * @return integer|string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Sets the id of this entry
     * @param integer|string $id
     * @return null
     */
    public function setId($id) {
        $this->id = $id;
    }

}
