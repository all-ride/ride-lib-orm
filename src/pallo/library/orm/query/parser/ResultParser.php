<?php

namespace pallo\library\orm\query\parser;

use pallo\library\database\result\DatabaseResult;
use pallo\library\orm\definition\ModelTable;
use pallo\library\orm\model\data\Data;
use pallo\library\orm\model\LocalizedModel;
use pallo\library\orm\model\Model;
use pallo\library\orm\OrmManager;

use \Exception;

/**
 * Parser to parse database results into orm results
 */
class ResultParser {

    /**
     * Meta definition of the model of the result
     * @var pallo\library\orm\model\ModelMeta
     */
    private $meta;

    /**
     * Empty data object for the model
     * @var mixed
     */
    private $data;

    /**
     * Array with empty data objects for relation models
     * @var array
     */
    private $modelData;

    /**
     * Constructs a new result parser
     * @param pallo\library\orm\model\Model $model Model of this result
     * @return null
     */
    public function __construct(Model $model) {
        $this->model = $model;
    }

    /**
     * Parses a database result into a orm result
     * @param pallo\library\database\result\DatabaseResult $databaseResult
     * @param string $indexFieldName Name of the field to index the result on
     * @return array Array with data objects
     */
    public function parseResult(OrmManager $orm, DatabaseResult $databaseResult, $indexFieldName = null) {
        $this->orm = $orm;
        $this->meta = $this->model->getMeta();

        $result = array();

        if ($indexFieldName === null) {
            $indexFieldName = ModelTable::PRIMARY_KEY;
        }

        foreach ($databaseResult as $row) {
            $data = $this->getDataObjectFromRow($row);

            if ($indexFieldName && isset($row[$indexFieldName])) {
                $result[$row[$indexFieldName]] = $data;
            } elseif ($indexFieldName && isset($row[QueryParser::ALIAS_SELF . QueryParser::ALIAS_SEPARATOR . $indexFieldName])) {
                $result[$row[QueryParser::ALIAS_SELF . QueryParser::ALIAS_SEPARATOR . $indexFieldName]] = $data;
            } else {
                $result[] = $data;
            }
        }

        return $result;
    }

    /**
     * Gets a data object from the provided database result row
     * @param array $row Database result row
     * @return array Array with data objects
     */
    private function getDataObjectFromRow($row) {
        $aliasses = array();

        $data = array();

        foreach ($row as $column => $value) {
            $positionAliasSeparator = strpos($column, QueryParser::ALIAS_SEPARATOR);
            if ($positionAliasSeparator === false) {
                if ($column != 'isDataLocalized' && $this->meta->getField($column)->getType() == 'serialize') {
                    if ($value) {
                        $value = unserialize($value);
                    } else {
                        $value = null;
                    }
                }

                $data[$column] = $value;

                continue;
            }

            $alias = substr($column, 0, $positionAliasSeparator);
            $fieldName = substr($column, $positionAliasSeparator + QueryParser::ALIAS_SEPARATOR_LENGTH);

            if ($alias == QueryParser::ALIAS_SELF && $fieldName != LocalizedModel::FIELD_LOCALE) {
                if ($this->meta->getField($fieldName)->getType() == 'serialize') {
                    if ($value) {
                        $value = unserialize($value);
                    } else {
                        $value = null;
                    }
                }

                $data[$fieldName] = $value;

                continue;
            }

            if (!isset($aliasses[$alias])) {
                $aliasses[$alias] = array();
            }

            $aliasses[$alias][$fieldName] = $value;
        }

        foreach ($aliasses as $fieldName => $value) {
            if (!isset($value[ModelTable::PRIMARY_KEY])) {
                $data[$fieldName] = $value;

                continue;
            }

            $containsValues = false;
            foreach ($value as $k => $v) {
                if ($v) {
                    $containsValues = true;

                    break;
                }
            }

            if ($containsValues) {
                $relationModelName = $this->meta->getRelationModelName($fieldName);
                $relationModel = $this->orm->getModel($relationModelName);

                $relationMeta = $relationModel->getMeta();
                $relationProperties = $relationMeta->getProperties();
                foreach ($relationProperties as $relationPropertyName => $relationProperty) {
                    if ($relationProperty->getType() == 'serialize' && isset($value[$relationPropertyName])) {
                        $value[$relationPropertyName] = unserialize($value[$relationPropertyName]);
                    }
                }

                $data[$fieldName] = $relationModel->createData($value);
            } else {
                $data[$fieldName] = null;
            }
        }

        return $this->model->createData($data);
    }

}