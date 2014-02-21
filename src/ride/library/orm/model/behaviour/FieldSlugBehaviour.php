<?php

namespace ride\library\orm\model\behaviour;

/**
 * Slug behaviour for a model based on the provided fields
 */
class FieldSlugBehaviour extends AbstractSlugBehaviour {

    /**
     * The fields to create the slug
     * @var array
     */
    protected $fields;

    /**
     * Constructs a new slug behaviour
     * @param string|array $fields The name or names of the fields to create
     * the slug
     * @return null
     */
    public function __construct($fields) {
        if (!is_array($fields)) {
            $fields = array($fields);
        }

        $this->fields = $fields;
    }

    /**
     * Gets the string to base the slug upon
     * @param mixed $data The data object of the model
     * @return string
     */
    protected function getSlugString($data) {
        $slug = '';

        foreach ($this->fields as $field) {
            if (!isset($data->$field) || empty($data->$field)) {
                continue;
            }

            $slug .= ($slug ? ' ' : '') . $data->$field;
        }

        return $slug;
    }

}