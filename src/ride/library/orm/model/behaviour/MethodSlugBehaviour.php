<?php

namespace ride\library\orm\model\behaviour;

/**
 * Slug behaviour for a model based on a method in the data container
 */
class MethodSlugBehaviour extends AbstractSlugBehaviour {

    /**
     * The method to create the slug
     * @var string
     */
    protected $method;

    /**
     * Constructs a new slug behaviour
     * @param string $method The name of the method to create the slug
     * @return null
     */
    public function __construct($method) {
        $this->method = $method;
    }

    /**
     * Gets the string to base the slug upon
     * @param mixed $data The data object of the model
     * @return string
     */
    protected function getSlugString($data) {
        $method = $this->method;

        return $data->$method();
    }

}