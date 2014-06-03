<?php

namespace ride\library\orm\definition\definer;

use ride\library\database\definition\definer\Definer;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\exception\OrmException;

use \Exception;

/**
 * Definer of models in the database
 */
class ModelDefiner {

    /**
     * Instance of the database definer
     * @var \ride\library\database\definition\definer\Definer
     */
    private $definer;

    /**
     * Constructs & new model definer
     * @param \ride\library\database\driver\Driver $connection
     * @return null
     */
    public function __construct(Definer $definer) {
        $this->definer = $definer;
    }

    /**
     * Gets a list of unused tables
     * @param array $usedModels Array with the used model name as key
     * @return array Array with table name as value
     */
    public function getUnusedTables(array $usedModels) {
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
    public function defineModels(array $modelTables) {
        $connection = $definer->getConnection();

        $isTransactionStarted = $connection->beginTransaction();
        try {
            $tables = array();

            foreach ($modelTables as $index => $modelTable) {
                if (!$modelTable instanceof ModelTable) {
                    throw new OrmException('Could not define the model: instance at index ' . $index . ' is not a ride\\library\\orm\\definition\\ModelTable');
                }

                $table = $modelTable->getDatabaseTable();

                $this->definer->defineTable($table);

                $tables[] = $table;
            }

            // foreign keys block truncate commands on MySQL :-(
//             foreach ($tables as $table) {
//                 $this->definer->defineForeignKeys($table);
//             }

            $connection->commitTransaction($isTransactionStarted);
        } catch (Exception $exception) {
            try {
                $connection->rollbackTransaction($isTransactionStarted);
            } catch (Exception $e) {

            }

            throw $exception;
        }
    }

}
