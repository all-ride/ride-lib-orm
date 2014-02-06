<?php

namespace pallo\library\orm\loader;

use pallo\library\cache\pool\CachePool;
use pallo\library\orm\exception\OrmException;
use pallo\library\orm\loader\io\ModelIO;
use pallo\library\orm\OrmManager;
use pallo\library\reflection\ReflectionHelper;

/**
 * Load the defined models into the OrmManager
 */
class ModelLoader {

    /**
     * Model IO to read the models
     * @var pallo\library\orm\loader\io\ModelIO
     */
    protected $io;

    /**
     * The loaded models
     * @var array
     */
    protected $models;

    /**
     * The register of the models
     * @var pallo\library\orm\loader\ModelRegister
     */
    protected $modelRegister;

    /**
     * Instance of the model manager
     * @var pallo\library\orm\OrmManager
     */
    protected $orm;

    /**
     * Instance of the cache pool for the models
     * @var pallo\library\cache\pool\CachePool
     */
    protected $cache;

    /**
     * Constructs a new model loader
     * @param pallo\library\orm\loader\io\ModelIO $io I/O to read the models
     * @return null
     */
    public function __construct(ModelIO $io, ReflectionHelper $reflectionHelper) {
        $this->io = $io;
        $this->reflectionHelper = $reflectionHelper;
        $this->models = array();
        $this->modelRegister = null;
        $this->orm = null;
        $this->cache = null;
    }

    /**
     * Sets the instance of the model manager to this
     * @param pallo\library\orm\OrmManager $orm Instance of the model
     * manager
     * @return null
     */
    public function setOrmManager(OrmManager $orm) {
        $this->orm = $orm;
    }

    /**
     * Sets the cache to this loader
     * @param pallo\library\cache\pool\CachePool $modelCache
     * @return null
     */
    public function setModelCache(CachePool $modelCache) {
        $this->cache = $modelCache;
    }

    /**
     * Gets the cache of this loader
     * @return pallo\library\cache\pool\CachePool|null
     */
    public function getModelCache() {
        return $this->cache;
    }

    /**
     * Gets the model register
     * @return pallo\library\orm\loader\ModelRegister
     */
    public function getModelRegister() {
        if (!$this->modelRegister) {
            $this->registerModels();
        }

        return $this->modelRegister;
    }

    /**
     * Sets the model register, the models registered will be saved to the
     * data source
     * @param pallo\library\orm\loader\ModelRegister $modelRegister
     * @return null
     */
    public function setModelRegister(ModelRegister $modelRegister) {
        $this->modelRegister = $modelRegister;

        $this->models = $modelRegister->getModels();

        $this->io->writeModels($this->models);

        $this->initializeModels();
    }

    /**
     * Checks if a model is loaded, if not an attempt to load is made
     * @param string $modelName Name of the model
     * @return boolean True if the model exists and is loaded, false otherwise
     * @throws pallo\library\orm\exception\OrmException when name is invalid
     */
    public function hasModel($modelName) {
        if (!is_string($modelName) || $modelName == '') {
            throw new OrmException('Provided model name is empty or invalid');
        }

        if (isset($this->models[$modelName])) {
            return true;
        } elseif ($this->modelRegister) {
            return false;
        }

        if (!$this->orm) {
            throw new OrmException('No OrmManager set to this loader');
        }

        $log = $this->orm->getLog();

        if ($this->cache) {
            $cacheItem = $this->cache->get($modelName);
            if ($cacheItem->isValid()) {
                $model = $cacheItem->getValue();
                $model->setOrmManager($this->orm);

                $this->models[$modelName] = $model;

                if ($log) {
                    $log->logDebug('Loaded model ' . $modelName . ' from cache', get_class($model), OrmManager::LOG_SOURCE);
                }

                return true;
            } elseif ($log) {
                $log->logDebug('Loading model ' . $modelName, 'skipped cache', OrmManager::LOG_SOURCE);
            }
        } elseif ($log) {
            $log->logDebug('Loading model ' . $modelName, null, OrmManager::LOG_SOURCE);
        }

        $this->registerModels();

        return isset($this->models[$modelName]);
    }

    /**
     * Gets a model by its name
     * @param string $modelName The name of the model
     * @return pallo\library\orm\model\Model
     * @throws pallo\library\orm\exception\OrmException when the model does not
     * exist
     */
    public function getModel($modelName) {
        if (!$this->hasModel($modelName)) {
            throw new OrmException('Model ' . $modelName . ' does not exist');
        }

        return $this->models[$modelName];
    }

    /**
     * Gets all the loaded models
     * @param boolean $loadAll Set to true to load all the models
     * @return array Array with the loaded models
     */
    public function getModels($loadAll = false) {
        if (!$loadAll || $this->modelRegister) {
            return $this->models;
        }

        if (!$this->orm) {
            throw new OrmException('No OrmManager set to this loader');
        }

        $this->registerModels();

        return $this->models;
    }

    /**
     * Registers all the defined models
     * @return boolean
     */
    private function registerModels() {
        $log = $this->orm->getLog();
        if ($log) {
            $log->logDebug('Reading all models', get_class($this->io), OrmManager::LOG_SOURCE);
        }

        $models = $this->io->readModels();

        $this->modelRegister = new ModelRegister($this->reflectionHelper);
        $this->modelRegister->registerModels($models);

        $this->models = $this->modelRegister->getModels();

        $this->initializeModels();
    }

    /**
     * Initialized the loaded models
     * @return null
     */
    private function initializeModels() {
        $log = $this->orm->getLog();

        foreach ($this->models as $modelName => $model) {
            if ($log) {
                $log->logDebug('Initializing model ' . $modelName, get_class($model), OrmManager::LOG_SOURCE);
            }

            // make sure the meta is parsed before caching the model
            $meta = $model->getMeta();
            $meta->parseMeta($this->orm);

            if ($this->cache) {
                $log->logDebug('Caching model ' . $modelName, '', OrmManager::LOG_SOURCE);

                $cacheItem = $this->cache->create($modelName);
                $cacheItem->setValue($model);

                $this->cache->set($cacheItem);

                $model->getMeta()->__wakeup();
            }

            $model->setOrmManager($this->orm);
        }
    }

}