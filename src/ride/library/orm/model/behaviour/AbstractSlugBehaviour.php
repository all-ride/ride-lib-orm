<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\model\LocalizedModel;
use ride\library\orm\model\Model;
use ride\library\validation\exception\ValidationException;
use ride\library\StringHelper;

/**
 * Interface to add extra behaviour to a model
 */
abstract class AbstractSlugBehaviour extends AbstractBehaviour {

    /**
     * Hook before validation of the data
     * @param \ride\library\orm\model\Model $model
     * @param mixed $data
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function preValidate(Model $model, $data, ValidationException $exception) {
        $slugString = $this->getSlugString($data);
        if (!$slugString) {
            return;
        }

        $slug = $baseSlug = StringHelper::safeString($slugString);
        $index = 1;

        if ($model->getMeta()->getField('slug')->isLocalized()) {
            $orm = $model->getOrmManager();
            $localizedModel = $orm->getModel($model->getName() . LocalizedModel::MODEL_SUFFIX);
        } else {
            $localizedModel = null;
        }

        do {
            if ($localizedModel) {
                $query = $localizedModel->createQuery();
                $query->addCondition('{slug} = %1%', $slug);


                if (isset($data->dataLocale)) {
                    $locale = $data->dataLocale;
                } else {
                    $locale = $orm->getLocale();
                }

                if ($data->id) {
                    $localizedId = $localizedModel->getLocalizedId($data->id, $locale);
                    if ($localizedId) {
                        $query->addCondition('{id} <> %1%', $localizedId);
                    }
                }
            } else {
                $query = $model->createQuery();
                $query->addCondition('{slug} = %1%', $slug);
                if ($data->id) {
                    $query->addCondition('{id} <> %1%', $data->id);
                }
            }

            $count = $query->count();
            if ($count) {
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
