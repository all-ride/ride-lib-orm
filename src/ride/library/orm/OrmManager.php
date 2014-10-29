<?php

namespace ride\library\orm;

use ride\library\cache\pool\CachePool;
use ride\library\database\DatabaseManager;
use ride\library\log\Log;
use ride\library\orm\cache\OrmCache;
use ride\library\orm\definition\definer\ModelDefiner;
use ride\library\orm\definition\FieldValidator;
use ride\library\orm\exception\OrmException;
use ride\library\orm\loader\ModelLoader;
use ride\library\orm\model\data\format\modifier\CapitalizeDataFormatModifier;
use ride\library\orm\model\data\format\modifier\DateDataFormatModifier;
use ride\library\orm\model\data\format\modifier\Nl2brDataFormatModifier;
use ride\library\orm\model\data\format\modifier\StripTagsDataFormatModifier;
use ride\library\orm\model\data\format\modifier\TruncateDataFormatModifier;
use ride\library\orm\model\data\format\DataFormatter;
use ride\library\orm\model\Model;
use ride\library\orm\query\parser\QueryParser;
use ride\library\orm\query\tokenizer\FieldTokenizer;
use ride\library\orm\query\CacheableModelQuery;
use ride\library\reflection\ReflectionHelper;
use ride\library\validation\factory\ValidationFactory;

/**
 * Manager of the ORM
 */
class OrmManager {

    /**
     * Default class for a model
     * @var string
     */
    const DEFAULT_MODEL = 'ride\\library\\orm\\model\\GenericModel';

    /**
     * Default namespace for the generated classes
     * @var string
     */
    const DEFAULT_NAMESPACE = 'ride\\library\\orm\\entry';

    /**
     * Interface of a model
     * @var string
     */
    const INTERFACE_MODEL = 'ride\\library\\orm\\model\\Model';

    /**
     * Source for the ORM log messages
     * @var string
     */
    const LOG_SOURCE = 'orm';

    /**
     * Instance of the reflection helper
     * @var \ride\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Instance of the database manager
     * @var \ride\library\database\DatabaseManager
     */
    protected $databaseManager;

    /**
     * Instance of the log
     * @var \ride\library\log\Log
     */
    protected $log;

    /**
     * Loader of the models
     * @var \ride\library\orm\loader\ModelLoader
     */
    protected $modelLoader;

    /**
     * Cache for the queries
     * @var \ride\library\cache\pool\CachePool
     */
    protected $queryCache;

    /**
     * Cache for the query results
     * @var \ride\library\cache\pool\CachePool
     */
    protected $resultCache;

    /**
     * Tokenizer for fields
     * @var \ride\library\orm\query\tokenizer\FieldTokenizer
     */
    protected $fieldTokenizer;

    /**
     * Parser for the queries
     * @var \ride\library\orm\query\parser\QueryParser
     */
    protected $queryParser;

    /**
     * Instance of the data formatter
     * @var \ride\library\orm\model\data\format\DataFormatter
     */
    protected $entryFormatter;

    /**
     * Instance of the validation factory
     * @var \ride\library\validation\factory\ValidationFactory
     */
    protected $validationFactory;

    /**
     * Code of the current locale
     * @var string
     */
    protected $locale;

    /**
     * Namespace for the generated classes
     * @var string
     */
    protected $defaultNamespace;

    /**
     * Constructs a new ORM manager
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, DatabaseManager $databaseManager, ModelLoader $modelLoader, ValidationFactory $validationFactory) {
        $this->reflectionHelper = $reflectionHelper;
        $this->databaseManager = $databaseManager;
        $this->validationFactory = $validationFactory;
        $this->log = null;

        $this->modelLoader = $modelLoader;
        $this->modelLoader->setOrmManager($this);

        $this->queryCache = null;
        $this->resultCache = null;

        $this->dataFormatter = null;
        $this->locale = 'en';

        $this->defaultNamespace = self::DEFAULT_NAMESPACE;
    }

    /**
     * Hook to implement get[ModelName]Model()
     * @param string $name Name of the invoked method
     * @param array $arguments Arguments for the method
     * @return \ride\library\orm\model\Model
     * @throws \ride\library\orm\exception\OrmException when the method is not
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
     * @return \ride\library\database\DatabaseManager
     */
    public function getDatabaseManager() {
        return $this->databaseManager;
    }

    /**
     * Gets the database connection
     * @param string $name Name of the connection, null for the default
     * connection
     * @return \ride\library\database\driver\Driver
     */
    public function getConnection($name = null) {
        return $this->databaseManager->getConnection($name);
    }

    /**
     * Sets the log
     * @param \ride\library\log\Log $log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Gets the log
     * @return \ride\library\log\Log
     */
    public function getLog() {
        return $this->log;
    }

    /**
     * Gets the model definer
     * @return \ride\library\orm\ModelDefiner
     */
    public function getDefiner() {
        $connection = $this->databaseManager->getConnection();
        $definer = $this->databaseManager->getDefiner($connection);

        return new ModelDefiner($definer);
    }

    /**
     * Gets the model cache
     * @return \ride\library\cache\pool\CachePool
     */
    public function getModelCache() {
        return $this->modelLoader->getModelCache();
    }

    /**
     * Sets the query cache
     * @param \ride\library\cache\pool\CachePool $queryCache
     * @return null
     */
    public function setQueryCache(CachePool $queryCache) {
        $this->queryCache = $queryCache;
    }

    /**
     * Gets the query cache
     * @return \ride\library\cache\pool\CachePool
     */
    public function getQueryCache() {
        return $this->queryCache;
    }

    /**
     * Sets the query result cache
     * @param \ride\library\cache\pool\CachePool $resultCache
     * @return null
     */
    public function setResultCache(CachePool $resultCache) {
        $this->resultCache = $resultCache;
    }

    /**
     * Gets the query result cache
     * @return \ride\library\cache\pool\CachePool
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

        $modelTables = array();

        $modelRegister = $this->modelLoader->getModelRegister();
        $models = $modelRegister->getModels();
        foreach ($models as $modelName => $model) {
            $modelTables[$modelName] = $model->getMeta()->getModelTable();
        }

        $this->getDefiner()->defineModels($modelTables);
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
     * @return \ride\library\orm\loader\ModelLoader
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
     * @throws \ride\library\orm\exception\OrmException when $modelName is not a string
     * @throws \ride\library\orm\exception\OrmException when $modelName is empty
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
     * @param string|\ride\library\orm\model\Model $model Model name or instance
     * @param string $locale Locale code of the data
     * @return \ride\library\orm\query\ModelQuery
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

    public function getValidationFactory() {
        return $this->validationFactory;
    }

    /**
     * Gets the field tokenizer
     * @return \ride\library\orm\query\tokenizer\FieldTokenizer
     */
    public function getFieldTokenizer() {
        if (!$this->fieldTokenizer) {
            $this->fieldTokenizer = new FieldTokenizer();
        }

        return $this->fieldTokenizer;
    }

    /**
     * Gets the query parser
     * @return \ride\library\orm\query\parser\QueryParser
     */
    public function getQueryParser() {
        if (!$this->queryParser) {
            $this->queryParser = new QueryParser($this);
        }

        return $this->queryParser;
    }

    /**
     * Gets the instance of the entry formatter
     * @return \ride\library\orm\entry\format\EntryFormatter
     */
    public function getEntryFormatter() {
        if (!$this->entryFormatter) {
            $modifiers = array(
                'capitalize' => new CapitalizeEntryFormatModifier(),
                'date' => new DateEntryFormatModifier(),
                'nl2br' => new Nl2brEntryFormatModifier(),
                'strip_tags' => new StripTagsEntryFormatModifier(),
                'truncate' => new TruncateEntryFormatModifier(),
            );

            $this->entryFormatter = new GenericEntryFormatter($this->reflectionHelper, $modifiers);
        }

        return $this->entryFormatter;
    }

    /**
     * Gets the default namespace for the generated classes
     * @return string
     */
    public function getDefaultNamespace() {
        return $this->defaultNamespace;
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

    /**
     * Gets the username of the current user
     * @return string|null
     */
    public function getUserName() {
        return null;
    }

}
