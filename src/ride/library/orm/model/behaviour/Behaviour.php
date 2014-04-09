<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\model\Model;
use ride\library\validation\exception\ValidationException;

/**
 * Interface to add extra behaviour to a model
 */
interface Behaviour {

    /**
     * Hook after creating a data container
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postCreateData(Model $model, $data);

    /**
     * Hook before validation of the data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function preValidate(Model $model, $data, ValidationException $exception);

    /**
     * Hook after validation of the data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function postValidate(Model $model, $data, ValidationException $exception);

    /**
     * Hook before inserting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preInsert(Model $model, $data);

    /**
     * Hook after inserting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postInsert(Model $model, $data);

    /**
     * Hook before updating data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preUpdate(Model $model, $data);

    /**
     * Hook after updating data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postUpdate(Model $model, $data);

    /**
     * Hook before updating a field
     * @param \ride\library\orm\model\Model $model
     * @param integer $id
     * @param string $fieldName
     * @param mixed $value
     * @return null
     */
    public function preUpdateField(Model $model, $id, $fieldName, $value);

    /**
     * Hook after updating a field
     * @param \ride\library\orm\model\Model $model
     * @param integer $id
     * @param string $fieldName
     * @param mixed $value
     * @return null
     */
    public function postUpdateField(Model $model, $id, $fieldName, $value);

    /**
     * Hook before deleting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function preDelete(Model $model, $data);

    /**
     * Hook after deleting data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @return null
     */
    public function postDelete(Model $model, $data);

}