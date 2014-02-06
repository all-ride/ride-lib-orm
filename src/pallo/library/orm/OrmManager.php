<?php

namespace pallo\library\orm;

use pallo\library\cache\pool\CachePool;
use pallo\library\database\DatabaseManager;
use pallo\library\log\Log;
use pallo\library\orm\cache\OrmCache;
use pallo\library\orm\definition\definer\ModelDefiner;
use pallo\library\orm\definition\FieldValidator;
use pallo\library\orm\exception\OrmException;
use pallo\library\orm\loader\ModelLoader;
use pallo\library\orm\model\data\format\modifier\CapitalizeDataFormatModifier;
use pallo\library\orm\model\data\format\modifier\DateDataFormatModifier;
use pallo\library\orm\model\data\format\modifier\Nl2brDataFormatModifier;
use pallo\library\orm\model\data\format\modifier\StripTagsDataFormatModifier;
use pallo\library\orm\model\data\format\modifier\TruncateDataFormatModifier;
use pallo\library\orm\model\data\format\DataFormatter;
use pallo\library\orm\model\Model;
use pallo\library\orm\query\parser\QueryParser;
use pallo\library\orm\query\tokenizer\FieldTokenizer;
use pallo\library\orm\query\CacheableModelQuery;
use pallo\library\orm\query\ModelQuery;
use pallo\library\reflection\ReflectionHelper;

/**
 * Manager of the ORM
 */
class OrmManager {

    /**
     * Default class for a model
     * @var string
     */
    const DEFAULT_MODEL = 'pallo\\library\\orm\\model\\GenericModel';

    /**
     * Interface of a model
     * @var string
     */
    const INTERFACE_MODEL = 'pallo\\library\\orm\\model\\Model';

    /**
     * Source for the ORM log messages
     * @var string
     */
    const LOG_SOURCE = 'orm';

    /**
     * Instance of the reflection helper
     * @var pallo\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Instance of the database manager
     * @var pallo\library\database\DatabaseManager
     */
    protected $databaseManager;

    /**
     * Instance of the log
     * @var pallo\library\log\Log
     */
    protected $log;

    /**
     * Loader of the models
     * @var pallo\library\orm\model\loader\ModelLoader
     */
    protected $modelLoader;

    /**
     * Cache for the queries
     * @var pallo\library\cache\pool\CachePool
     */
    protected $queryCache;

    /**
     * Cache for the query results
     * @var pallo\library\cache\pool\CachePool
     */
    protected $resultCache;

    /**
     * Tokenizer for fields
     * @var pallo\library\orm\query\tokenizer\FieldTokenizer
     */
    protected $fieldTokenizer;

    /**
     * Parser for the queries
     * @var pallo\library\orm\query\parser\QueryParser
     */
    protected $queryParser;

    /**
     * Instance of the data formatter
     * @var pallo\library\orm\model\data\format\DataFormatter
     */
    protected $dataFormatter;

    /**
     * The code of the current locale
     * @var string
     */
    protected $locale;

    /**
     * Constructs a new ORM manager
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, DatabaseManager $databaseManager, ModelLoader $modelLoader) {
        $this->reflectionHelper = $reflectionHelper;
        $this->databaseManager = $databaseManager;
        $this->log = null;

        $this->modelLoader = $modelLoader;
        $this->modelLoader->setOrmManager($this);

        $this->queryCache = null;
        $this->resultCache = null;

        $this->dataFormatter = null;
        $this->locale = 'en';
    }

    /**
     * Hook to implement get[ModelName]Model()
     * @param string $name Name of the invoked method
     * @param array $arguments Arguments for the method
     * @return pallo\library\orm\model\Model
     * @throws pallo\library\orm\exception\OrmException when the method is not
     * a get[ModelName]Model call
     */
    public function __call($name, $arguments) {
        if (strpos($name, 'get') !== 0 && strpos(substr($name, -5), 'Model') !== 0) {
            throw new OrmException('Could not invoke ' . $name . ': method does not exist');
        }

        return $this->getModel(substr($name, 3, -5));
    }

    /**
     * Gets the database manager
     * @return pallo\library\database\DatabaseManager
     */
    public function getDatabaseManager() {
        return $this->databaseManager;
    }

    /**
     * Gets the database connection
     * @param string $name Name of the connection, null for the default
     * connection
     * @return pallo\library\database\driver\Driver
     */
    public function getConnection($name = null) {
        return $this->databaseManager->getConnection($name);
    }

    /**
     * Sets the log
     * @param pallo\library\log\Log $log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Gets the log
     * @return pallo\library\log\Log
     */
    public function getLog() {
        return $this->log;
    }

    /**
     * Gets the model definer
     * @return pallo\library\orm\ModelDefiner
     */
    public function getDefiner() {
        $connection = $this->databaseManager->getConnection();
        $definer = $this->databaseManager->getDefiner($connection);

        return new ModelDefiner($definer);
    }

    /**
     * Gets the model cache
     * @return pallo\library\cache\pool\CachePool
     */
    public function getModelCache() {
        return $this->modelLoader->getModelCache();
    }

    /**
     * Sets the query cache
     * @param pallo\library\cache\pool\CachePool $queryCache
     * @return null
     */
    public function setQueryCache(CachePool $queryCache) {
        $this->queryCache = $queryCache;
    }

    /**
     * Gets the query cache
     * @return pallo\library\cache\pool\CachePool
     */
    public function getQueryCache() {
        return $this->queryCache;
    }

    /**
     * Sets the query result cache
     * @param pallo\library\cache\pool\CachePool $resultCache
     * @return null
     */
    public function setResultCache(CachePool $resultCache) {
        $this->resultCache = $resultCache;
    }

    /**
     * Gets the query result cache
     * @return pallo\library\cache\pool\CachePool
     */
    public function getResultCache() {
        return $this->resultCache;
    }

    /**
     * Clears the cache
     * @return null
     */
    public function clearCache() {
        $modelCache = $this->modelLoader->getModelCache();
        if ($modelCache) {
            $modelCache->flush();
        }

        if ($this->queryCache) {
            $this->queryCache->flush();
        }

        if ($this->resultCache) {
            $this->resultCache->flush();
        }
    }

    /**
     * Defines all the models in the database
     * @return null
     */
    public function defineModels() {
        $this->clearCache();

        $modelRegister = $this->modelLoader->getModelRegister();
        $models = $modelRegister->getModels();

        $this->getDefiner()->defineModels($models);
    }

    /**
     * Gets a list of all database tables which are not used by the ORM
     * @return array Array with the table names
     */
    public function getUnusedTables() {
        $this->clearCache();

        $modelRegister = $this->modelLoader->getModelRegister();
        $models = $modelRegister->getModels();

        return $this->getDefiner()->getUnusedTables($models);
    }

    /**
     * Gets the model loader
     * @return pallo\library\orm\loader\ModelLoader
     */
    public function getModelLoader() {
        return $this->modelLoader;
    }

    /**
     * Checks whether a model exists or not
     * @param string $modelName Name of the model
     * @return boolean True when the model exists, false otherwise
     */
    public function hasModel($modelName) {
        return $this->modelLoader->hasModel($modelName);
    }

    /**
     * Gets a model
     * @param string $modelName
     * @return Model
     * @throws pallo\ZiboException when $modelName is not a string
     * @throws pallo\library\orm\exception\OrmException when $modelName is empty
     * or when the model does not exists
     */
    public function getModel($modelName) {
        return $this->modelLoader->getModel($modelName);
    }

    /**
     * Gets all the models
     * @param boolean $loadAll Set to false to retrieve only the loaded models
     * @return array
     */
    public function getModels($loadAll = true) {
        return $this->modelLoader->getModels($loadAll);
    }

    /**
     * Creates a query for the provided model
     * @param string|pallo\library\orm\model\Model $model Model name or instance
     * @param string $locale Locale code of the data
     * @return pallo\library\orm\query\ModelQuery
     */
    public function createQuery($model, $locale = null) {
        if ($locale === null) {
            $locale = $this->getLocale();
        }

        if (is_string($model)) {
            $model = $this->getModel($model);
        }

        return new CacheableModelQuery($model, $this->getLocales(), $locale);
    }

    /**
     * Gets the field tokenizer
     * @return pallo\library\orm\query\tokenizer\FieldTokenizer
     */
    public function getFieldTokenizer() {
        if (!$this->fieldTokenizer) {
            $this->fieldTokenizer = new FieldTokenizer();
        }

        return $this->fieldTokenizer;
    }

    /**
     * Gets the query parser
     * @return pallo\library\orm\query\parser\QueryParser
     */
    public function getQueryParser() {
        if (!$this->queryParser) {
            $this->queryParser = new QueryParser($this);
        }

        return $this->queryParser;
    }

    /**
     * Gets the instance of the data formatter
     * @return \pallo\library\orm\DataFormatter
     */
    public function getDataFormatter() {
        if (!$this->dataFormatter) {
            $modifiers = array(
                'capitalize' => new CapitalizeDataFormatModifier(),
                'date' => new DateDataFormatModifier(),
                'nl2br' => new Nl2brDataFormatModifier(),
                'strip_tags' => new StripTagsDataFormatModifier(),
                'truncate' => new TruncateDataFormatModifier(),
            );

            $this->dataFormatter = new DataFormatter($this->reflectionHelper, $modifiers);
        }

        return $this->dataFormatter;
    }

    /**
     * Gets an array with the available locales
     * @return array Array with the locale code as key
     */
    public function getLocales() {
        return array('en' => true);
    }

    /**
     * Gets the current locale
     * @return string Code of the locale
     */
    public function getLocale() {
        return 'en';
    }

}