<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\Model;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\ValidationError;

/**
 * Behaviour to force a unique value for a field
 */
class UniqueBehaviour extends AbstractBehaviour {

    /**
     * Code for the error when the value is not unique
     * @var string
     */
    const VALIDATION_ERROR = 'error.validation.unique';

    /**
     * Message for the error when the value is not unique
     * @var string
     */
    const VALIDATION_MESSAGE = '%value% is already used by another record.';

    /**
     * Name of the unique field
     * @var string
     */
    protected $field;

    /**
     * Conditions for the unique records
     * @var array
     */
    protected $conditionFields;

    /**
     * Translation key for the validation error
     * @var string
     */
    protected $error;

    /**
     * Constructs a new unique behaviour
     * @param string $field Name of the unique field
     * @param string|array $conditionFields Name or names of fields which should
     * match the data's field value
     * @return null
     */
    public function __construct($field, $conditionFields = null) {
        if ($conditionFields && !is_array($conditionFields)) {
            $conditionFields = array($conditionFields);
        }

        $this->field = $field;
        $this->conditionFields = $conditionFields;

        $this->error = self::VALIDATION_ERROR;
    }

    /**
     * Sets the validation error for the unique validation
     * @param string $error Translation key for the validation error
     * @return null
     */
    public function setValidationError($error) {
        $this->error = $error;
    }

    /**
     * Hook before validation of the data
     * @param \ride\library\orm\model\Model $model
     * @param \ride\library\validation\exception\ValidationException $exception
     * @param mixed $data
     * @return null
     */
    public function postValidate(Model $model, $data, ValidationException $exception) {
        $value = $model->getReflectionHelper()->getProperty($data, $this->field);

        if (!$value) {
            return;
        }

        $query = $this->createQuery($model, $data);
        if (!$query->count()) {
            return;
        }

        $error = new ValidationError(
            $this->error,
			self::VALIDATION_MESSAGE,
            array('value' => $value)
        );

        $exception->addErrors($field, array($error));
    }

    /**
     * Creates the query to check for uniqueness
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @return \ride\library\orm\query\ModelQuery
     */
    protected function createQuery(Model $model, $data) {
        $reflectionHelper = $model->getReflectionHelper();

        $query = $model->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFetchUnlocalizedData(true);

        $query->setFields('{id}, {' . $this->field . '}');

        $query->addCondition('{' . $this->field . '} = %1%', $reflectionHelper->getProperty($data, $this->field));

        $id = $reflectionHelper->getProperty($data, ModelTable::PRIMARY_KEY);
        if ($id) {
            $query->addCondition('{id} <> %1%', $id);
        }

        if ($this->conditionFields) {
            foreach ($this->conditionFields as $conditionField) {
                $query->addCondition('{' . $conditionField . '} = %1%', $reflectionHelper->getProperty($data, $conditionField));
            }
        }

        return $query;
    }

}