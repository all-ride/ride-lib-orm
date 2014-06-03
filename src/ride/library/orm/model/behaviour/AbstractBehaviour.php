<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\model\Model;
use ride\library\validation\exception\ValidationException;

/**
 * Interface to add extra behaviour to a model
 */
class AbstractBehaviour implements Behaviour {

    /**
     * Hook after creating an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postCreateEntry(Model $model, $entry) {

    }

    /**
     * Hook before validation of an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function preValidate(Model $model, $entry, ValidationException $exception) {

    }

    /**
     * Hook before validation of an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function postValidate(Model $model, $entry, ValidationException $exception) {

    }

    /**
     * Hook before inserting an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preInsert(Model $model, $entry) {

    }

    /**
     * Hook after inserting an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function postInsert(Model $model, $entry) {

    }

    /**
     * Hook before updating an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preUpdate(Model $model, $entry) {

    }

    /**
     * Hook after updating an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
     public function postUpdate(Model $model, $entry) {

     }

    /**
     * Hook before deleting an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
    public function preDelete(Model $model, $entry) {

    }

    /**
     * Hook after deleting an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @return null
     */
     public function postDelete(Model $model, $entry) {

     }

}
