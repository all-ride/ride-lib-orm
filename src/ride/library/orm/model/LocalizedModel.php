<?php

namespace ride\library\orm\model;

use ride\library\orm\definition\ModelTable;
use ride\library\orm\exception\OrmException;

/**
 * Model for the localized fields of a model
 */
class LocalizedModel extends GenericModel {

    /**
     * Suffix for the localized models
     * @var string
     */
    const MODEL_SUFFIX = 'Localized';

    /**
     * Field name of the unlocalized data
     * @var string
     */
    const FIELD_DATA = 'dataId';

    /**
     * Field name of the locale field
     * @var string
     */
    const FIELD_LOCALE = 'dataLocale';

    /**
     * Saves the localized data
     * @param mixed $data Localized data object
     * @return null
     */
    protected function saveData($data) {
        if (!empty($data->id)) {
            parent::saveData($data);
        }

        $data->id = $this->getLocalizedId($data->dataId, $data->dataLocale);

        parent::saveData($data);
    }

    /**
     * Gets the id of the localized data
     * @param integer $id Primary key of the unlocalized data
     * @param string $locale Locale code of the localized data
     * @return null|integer The primary key of the localized data if found, null otherwise
     */
    public function getLocalizedId($id, $locale) {
        $query = $this->createLocalizedQuery($id, $locale, 0);
        $query->setFields('{id}');

        $data = $query->queryFirst();

        if ($data != null) {
            return $data->id;
        }

        return null;
    }

    /**
     * Gets the ids of the localized data
     * @param integer $id Primary key of the unlocalized data
     * @return array Array with the locale code as key and the primary key of the localized data as value
     */
    public function getLocalizedIds($id) {
        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{id}, {dataLocale}');
        $query->addCondition('{dataId} = %1%', $id);

        $result = $query->query();

        $ids = array();
        foreach ($result as $data) {
            $ids[$data->dataLocale] = $data->id;
        }

        return $ids;
    }

    /**
     * Gets the localized data
     * @param integer $id Primary key of the unlocalized data
     * @param string $locale Locale code of the localized data
     * @param integer $recursiveDepth Depth for the recursive relations
     * @return null|mixed The localized data if found, null otherwise
     */
    public function getLocalizedData($id, $locale, $recursiveDepth = 1, $fields = null) {
        $query = $this->createLocalizedQuery($id, $locale, $recursiveDepth);

        if ($fields) {
            $query->setFields($fields);
        }

        return $query->queryFirst();
    }

    /**
     * Deletes the localized data
     * @param integer $id Primary key of the unlocalized data
     * @return null
     * @throws ride\ZiboException when the provided id is empty or invalid
     */
    public function deleteLocalizedData($id) {
        if (empty($id)) {
            throw new OrmException('Provided id is empty or invalid');
        }

        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{id}');
        $query->addCondition('{dataId} = %1%', $id);

        $result = $query->query();

        $this->delete($result);
    }

    /**
     * Creates a query for a specific data object and locale
     * @param integer $id Primary key of the data
     * @param string $locale Locale code for the localized data
     * @param integer $recursiveDepth Depth for the recursive relations
     * @return ride\library\orm\query\ModelQuery
     * @throws ride\ZiboException when the id is empty or invalid
     * @throws ride\ZiboException when the locale is empty or invalid
     */
    protected function createLocalizedQuery($id, $locale, $recursiveDepth = 1) {
        if (empty($id)) {
            throw new OrmException('Provided id is empty');
        }
        if (!is_string($locale) || $locale == '') {
            throw new OrmException('Provided locale code is empty or invalid');
        }

        $query = $this->createQuery();
        $query->setRecursiveDepth($recursiveDepth);
        $query->addCondition('{dataId} = %1% AND {dataLocale} = %2%', $id, $locale);

        return $query;
    }

}