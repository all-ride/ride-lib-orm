<?php

namespace ride\library\orm\entry\constraint;

use ride\library\orm\entry\EntryProxy;
use ride\library\validation\constraint\GenericConstraint;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\filter\Filter;

/**
 * Generic constraint which takes entry proxies into account
 */
class EntryConstraint extends GenericConstraint {

    /**
     * Constrains the provided instance
     * @param array|object $isntance Instance to be validated
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return array|object Filtered and validated instance
     * @throws \ride\library\validation\exception\ValidationException when the
     * instance could not be validated and no exception is provided
     */
    public function constrain($instance, ValidationException $exception = null) {
        $isProxy = $instance instanceof EntryProxy;

        foreach ($this->filters as $property => $filters) {
            if ($isProxy && !$instance->isFieldSet($property)) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($instance, $property);

            foreach ($filters as $filter) {
                $value = $filter->filter($value);
            }

            $this->reflectionHelper->setProperty($instance, $property, $value);
        }

        if ($exception) {
            $throwException = false;
        } else {
            $throwException = true;

            $exception = new ValidationException();
        }

        foreach ($this->validators as $property => $validators) {
            if ($isProxy && !$instance->isFieldSet($property)) {
                continue;
            }

            $value = $this->reflectionHelper->getProperty($instance, $property);

            foreach ($validators as $validator) {
                if ($validator->isValid($value)) {
                    continue;
                }

                $exception->addErrors($property, $validator->getErrors());
            }
        }

        foreach ($this->constraints as $constraint) {
            $instance = $constraint->constrain($instance, $exception);
        }

        if ($throwException && $exception->hasErrors()) {
            throw $exception;
        }

        return $instance;
    }

}
