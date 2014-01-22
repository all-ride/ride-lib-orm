<?php

namespace pallo\library\orm\model\behaviour;

use pallo\library\orm\model\Model;
use pallo\library\validation\exception\ValidationException;
use pallo\library\String;

/**
 * Interface to add extra behaviour to a model
 */
abstract class AbstractSlugBehaviour extends AbstractBehaviour {

    /**
     * Hook before validation of the data
     * @param pallo\library\orm\model\Model $model
     * @param mixed $data
     * @param pallo\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function preValidate(Model $model, $data, ValidationException $exception) {
        $slugString = $this->getSlugString($data);
        if (!$slugString) {
            return;
        }

        $slugString = new String($slugString);
        $slug = $baseSlug = strtolower($slugString->safeString());
        $index = 1;

        do {
            $query = $model->createQuery();
            $query->addCondition('{slug} = %1%', $slug);
            if ($data->id) {
                $query->addCondition('{id} <> %1%', $data->id);
            }

            if ($query->count()) {
                $slug = $baseSlug . '-' . $index;
                $index++;
            } else {
                break;
            }
        } while (true);

        $data->slug = $slug;
    }

    /**
     * Gets the string to base the slug upon
     * @param mixed $data The data object of the model
     * @return string
     */
    abstract protected function getSlugString($data);

}