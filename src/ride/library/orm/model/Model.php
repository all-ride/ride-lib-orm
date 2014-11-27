<?php

namespace ride\library\orm\model;

use ride\library\orm\meta\ModelMeta;
use ride\library\orm\OrmManager;
use ride\library\reflection\ReflectionHelper;

/**
 * Interface for a data model
 */
interface Model {

    /**
     * Constructs a new data model
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param ModelMeta $modelMeta Meta data of the model
     * @param array $behaviours
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, ModelMeta $modelMeta, array $behaviours = array());

    /**
     * Sets the model manager to this model
     * @param \ride\library\orm\OrmManager $orm
     * @return null
     */
    public function setOrmManager(OrmManager $orm);

    /**
     * Gets the model manager from this model
     * @return \ride\library\orm\OrmManager
     */
    public function getOrmManager();

    /**
     * Gets the name of this model
     * @return string
     */
    public function getName();

    /**
     * Gets the meta data of this model
     * @return \ride\library\orm\meta\ModelMeta
     */
    public function getMeta();

    /**
     * Gets the database result parser of this model
     * @return \ride\library\orm\query\parser\ResultParser
     */
    public function getResultParser();

    /**
     * Creates a new entry for this model
     * @param array $properties Initial properties for the entry instance
     * @return mixed A new entry instance for this model
     */
    public function createEntry(array $properties = array());

    /**
     * Creates an entry proxy for this model
     * @param integer|string $id Primary key of the entry
     * @param string|null $locale Code of the locale
     * @param array $properties Known properties of the entry instance
     * @return mixed An entry proxy instance for this model
     */
    public function createProxy($id, $locale = null, array $properties = array());

    /**
     * Creates a query for this model
     * @param string $locale Locale code of the data
     * @return \ride\library\orm\query\ModelQuery
     */
    public function createQuery($locale = null);

    /**
     * Gets an entry by it's primary key
     * @param integer|string $id Id of the data
     * @param string $locale Locale code
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @param integer $recursiveDepth Recursive depth of the query
     * @return mixed Instance of the entry if found, null otherwise
     */
    public function getById($id, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0);

    /**
     * Finds an entry in this model
     * @param array $options Options for the query
     * <ul>
     * <li>filter: array with the field name as key and the filter value as
     * value</li>
     * <li>match: array with the field name as key and the search query as
     * value</li>
     * <li>order: array with field and direction as key</li>
     * </li>
     * @param string $locale Locale code
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @param integer $recursiveDepth Recursive depth of the query
     * @return mixed Instance of the entry if found, null otherwise
     */
    public function getBy(array $options, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0);

    /**
     * Finds entries in this model
     * @param array $options Options for the query
     * <ul>
     * <li>filter: array with the field name as key and the filter value as
     * value</li>
     * <li>match: array with the field name as key and the search query as
     * value</li>
     * <li>order: array with field and direction as key</li>
     * <li>limit: number of entries to fetch</li>
     * <li>page: page number</li>
     * </li>
     * @param string $locale Code of the locale
     * @param boolean $fetchUnlocalized Flag to see if unlocalized entries
     * should be fetched
     * @param integer $recursiveDepth Recursive depth of the query
     * @return array
     */
    public function find(array $options = null, $locale = null, $fetchUnlocalized = false, $recursiveDepth = 0);

    /**
     * Validates an entry of this model
     * @param mixed $entry Entry instance or entry properties of this model
     * @return null
     * @throws \ride\library\validation\exception\ValidationException when one
     * of the fields is not validated
     */
    public function validate($entry);

    /**
     * Saves an entry to the model
     * @param mixed $entry An entry instance or an array of entry instances
     * @return null
     * @throws \Exception when the entry could not be saved
     */
    public function save($entry);

    /**
     * Deletes an entry from the model
     * @param mixed $entry An entry instance or an array with entry instances
     * @return null
     * @throws \Exception when the entry could not be deleted
     */
    public function delete($entry);

    /**
     * Deletes a localized version of a entry from the model
     * @param mixed $entry Entry instance or an array with entry instances
     * @param $locale Code of the locale
     * @return null
     * @throws \Exception when the entry could not be deleted
     */
    public function deleteLocalized($entry, $locale);

    /**
     * Clears the cache of this model
     * @return null
     */
    public function clearCache();

}
