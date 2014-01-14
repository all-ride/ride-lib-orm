<?php

namespace pallo\library\orm\definition\definer;

use pallo\library\database\definition\definer\Definer;
use pallo\library\database\driver\Driver;
use pallo\library\orm\model\Model;

use \Exception;

/**
 * Definer of models in the database
 */
class ModelDefiner {

    /**
     * The database definer
     * @var pallo\library\database\definition\definer\Definer
     */
    private $definer;

    /**
     * The database connection
     * @var pallo\library\database\driver\Driver
     */
    private $connection;

    /**
     * Constructs & new model definer
     * @param pallo\library\database\driver\Driver $connection
     * @return null
     */
    public function __construct(Definer $definer) {
        $this->definer = $definer;
        $this->connection = $definer->getConnection();
    }

    /**
     * Gets a list of unused tables
     * @param array $models Array with the used models
     * @return array Array with table names
     */
    public function getUnusedTables(array $models) {
        $tables = $this->definer->getTableList();

        foreach ($tables as $index => $table) {
            if (isset($models[$table])) {
                unset($tables[$index]);
            }
        }

        return $tables;
    }

    /**
     * Creates or alters the tables in the database of the provided models
     * @param array $models Array with Model objects
     * @return null
     */
    public function defineModels(array $models) {
        $isTransactionStarted = $this->connection->beginTransaction();
        try {
            $tables = array();

            foreach ($models as $model) {
                $table = $this->getDatabaseTable($model);

                $this->definer->defineTable($table);

                $tables[] = $table;
            }

            // foreign keys block truncate commands on MySQL :-(
//             foreach ($tables as $table) {
//                 $this->definer->defineForeignKeys($table);
//             }

            $this->connection->commitTransaction($isTransactionStarted);
        } catch (Exception $exception) {
            try {
                $this->connection->rollbackTransaction($isTransactionStarted);
            } catch (Exception $e) {

            }

            throw $exception;
        }
    }

    /**
     * Gets the database table definition of the provided model
     * @param pallo\library\orm\model\Model $model
     * @return pallo\library\database\definition\Table
     */
    private function getDatabaseTable(Model $model) {
        $meta = $model->getMeta();

        $modelTable = $meta->getModelTable();

        $databaseTable = $modelTable->getDatabaseTable();

        return $databaseTable;
    }

}