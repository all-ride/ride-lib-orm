<?php

namespace ride\library\orm\model;

use ride\library\orm\model\meta\ModelMeta;
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
     * @return ModelMeta
     */
    public function getMeta();

    /**
     * Gets the database result parser of this model
     * @return \ride\library\orm\query\parser\ResultParser
     */
    public function getResultParser();

    /**
     * Creates a new data object for this model
     * @param array $properties Initial properties for the instance
     * @return mixed A new data object for this model
     */
    public function createData(array $properties = array());

    /**
     * Creates a model query for this model
     * @param string $locale Locale code of the data
     * @return \ride\library\orm\query\ModelQuery
     */
    public function createQuery($locale = null);

    /**
     * Validates a data object of this model
     * @param mixed $data Data object of the model
     * @return null
     * @throws \ride\library\validation\exception\ValidationException when one of the fields is not validated
     */
    public function validate($data);

    /**
     * Saves data to the model
     * @param mixed $data A data object or an array of data objects when no id argument is provided, the value for the field otherwise
     * @param string $fieldName Name of the field to save
     * @param int $id Primary key of the data to save, $data will be considered as the value for the provided field name
     * @param string $locale The locale of the value
     * @return null
     * @throws Exception when the data could not be saved
     */
    public function save($data, $fieldName = null, $id = null, $locale = null);

    /**
     * Deletes data from the model
     * @param mixed $data Primary key of the data, a data object or an array with the previous as value
     * @return null
     * @throws Exception when the data could not be deleted
     */
    public function delete($data);

    /**
     * Clears the cache of this model
     * @return null
     */
    public function clearCache();

}