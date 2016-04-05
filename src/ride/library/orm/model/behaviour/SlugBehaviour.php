<?php

namespace ride\library\orm\model\behaviour;

use ride\library\orm\entry\LocalizedEntry;
use ride\library\orm\entry\SluggedEntry;
use ride\library\orm\model\Model;
use ride\library\validation\exception\ValidationException;
use ride\library\StringHelper;

/**
 * Behaviour to generate a unique slug for your entry
 */
class SlugBehaviour extends AbstractBehaviour {

    /**
     * Hook before validation of an entry
     * @param \ride\library\orm\model\Model $model
     * @param mixed $entry
     * @param \ride\library\validation\exception\ValidationException $exception
     * @return null
     */
    public function preValidate(Model $model, $entry, ValidationException $exception) {
        if (!$entry instanceof SluggedEntry) {
            return;
        }

        $slugBase = $entry->getSlugBase();
        if (!$slugBase) {
            return;
        }

        $slugBase = str_replace('.', '', StringHelper::safeString($slugBase));
        $slug = $slugBase;
        $index = 1;

        $entryId = $entry->getId();

        $locale = null;
        if ($entry instanceof LocalizedEntry) {
            $locale = $entry->getLocale();
        }
        if (!$locale) {
            $locale = $model->getOrmManager()->getLocale();
        }

        $isLocalized = $model->getMeta()->getField('slug')->isLocalized();
        if ($isLocalized) {
            $queryModel = $model->getLocalizedModel();

            if ($entryId) {
                $queryId = $queryModel->getLocalizedId($entryId, $locale);
            } else {
                $queryId = null;
            }
        } else {
            $queryModel = $model;
            $queryId = $entryId;
        }

        do {
            $query = $queryModel->createQuery();
            $query->addCondition('{slug} = %1%', $slug);

            if ($queryId) {
                $query->addCondition('{id} <> %1%', $queryId);
            }

            if ($isLocalized) {
                $query->addCondition('{locale} = %1%', $locale);
            }

            $count = $query->count();
            if (!$count) {
                break;
            }

            $slug = $slugBase . '-' . $index;

            $index++;
        } while (true);

        $entry->setSlug($slug);
    }

}
