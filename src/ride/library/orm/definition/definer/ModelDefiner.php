<?php

namespace ride\library\orm\definition\definer;

use ride\library\database\definition\definer\Definer;
use ride\library\database\driver\Driver;
use ride\library\orm\model\Model;

use \Exception;

/**
 * Definer of models in the database
 */
class ModelDefiner {

    /**
     * The database definer
     * @var ride\library\database\definition\definer\Definer
     */
    private $definer;

    /**
     * The database connection
     * @var ride\library\database\driver\Driver
     */
    private $connection;

    /**
     * Constructs & new model definer
     * @param ride\library\database\driver\Driver $connection
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
     * @param ride\library\orm\model\Model $model
     * @return ride\library\database\definition\Table
     */
    private function getDatabaseTable(Model $model) {
        $meta = $model->getMeta();

        $modelTable = $meta->getModelTable();

        $databaseTable = $modelTable->getDatabaseTable();

        return $databaseTable;
    }

}