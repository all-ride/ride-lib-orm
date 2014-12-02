<?php

namespace ride\library\orm\model;

use ride\library\orm\entry\proxy\EntryProxy;
use ride\library\orm\entry\LocalizedEntry;
use ride\library\orm\exception\OrmException;

/**
 * Model for the localized fields of a model
 */
class LocalizedModel extends GenericModel {

    /**
     * Field name of the unlocalized (main) entry field
     * @var string
     */
    const FIELD_ENTRY = 'entry';

    /**
     * Field name of the locale field
     * @var string
     */
    const FIELD_LOCALE = 'locale';

    /**
     * Saves the localized entry
     * @param mixed $entry Localized entry
     * @return null
     */
    protected function saveEntry($entry) {
        if ($entry->getId()) {
            parent::saveEntry($entry);

            return;
        }

        $locale = $this->reflectionHelper->getProperty($entry, self::FIELD_LOCALE);
        $entry->setId($this->getLocalizedId($entry->getEntry()->getId(), $locale));

        if ($entry instanceof EntryProxy) {
            $stateLocale = $entry->getFieldState('locale');
            if ($locale && $stateLocale && $locale != $stateLocale) {
                $entry->setEntryState(array());
            }
        }

        parent::saveEntry($entry);
    }

    /**
     * Gets the primary key of the localized entry
     * @param integer $id Primary key of the unlocalized entry
     * @param string $locale Locale code of the localized entry
     * @return null|integer Primary key of the localized entry if found, null
     * otherwise
     */
    public function getLocalizedId($id, $locale) {
        $query = $this->createLocalizedQuery($id, $locale, 0);
        $query->setFields('{id}');

        $entry = $query->queryFirst();
        if ($entry) {
            return $entry->getId();
        }

        return null;
    }

    /**
     * Gets the primary keys of the localized entry
     * @param integer $id Primary key of the unlocalized entry
     * @return array Array with the locale code as key and the primary key of
     * the localized entry as value
     */
    public function getLocalizedIds($id) {
        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{id}, {locale}');
        $query->addCondition('{entry} = %1%', $id);

        $entries = $query->query();

        $ids = array();
        foreach ($entries as $entry) {
            $ids[$entry->getLocale()] = $entry->getId();
        }

        return $ids;
    }

    /**
     * Gets the localized entry
     * @param integer $id Primary key of the unlocalized entry
     * @param string $locale Locale code of the localized entry
     * @param integer $recursiveDepth Depth for the recursive relations
     * @return null|mixed Localized entry if found, null otherwise
     */
    public function getLocalizedEntry($id, $locale, $recursiveDepth = 0, $fields = null) {
        if (!$id) {
            return null;
        }

        $query = $this->createLocalizedQuery($id, $locale, $recursiveDepth);

        if ($fields) {
            $query->setFields($fields);
        }

        return $query->queryFirst();
    }

    /**
     * Deletes the localized entry
     * @param integer $id Primary key of the unlocalized entry
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the provided id is
     * empty or invalid
     */
    public function deleteEntryLocalization($id) {
        if (empty($id)) {
            throw new OrmException('Provided id is empty or invalid');
        }

        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{id}');
        $query->addCondition('{entry} = %1%', $id);

        $result = $query->query();

        $this->delete($result);
    }

    /**
     * Creates a query for a specific entry and locale
     * @param integer $id Primary key of the entry
     * @param string $locale Locale code for the localized entry
     * @param integer $recursiveDepth Depth for the recursive relations
     * @return \ride\library\orm\query\ModelQuery
     * @throws \ride\library\orm\exception\OrmException when the id is empty or
     * invalid
     * @throws \ride\library\orm\exception\OrmException when the locale is empty
     * or invalid
     */
    protected function createLocalizedQuery($id, $locale, $recursiveDepth = 0) {
        if (empty($id)) {
            throw new OrmException('Provided id is empty');
        }
        if (!is_string($locale) || $locale == '') {
            throw new OrmException('Provided locale code is empty or invalid');
        }

        $query = $this->createQuery();
        $query->setRecursiveDepth($recursiveDepth);
        $query->addCondition('{' . self::FIELD_ENTRY . '} = %1% AND {' . self::FIELD_LOCALE . '} = %2%', $id, $locale);

        return $query;
    }

}
