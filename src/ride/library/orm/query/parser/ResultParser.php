<?php

namespace ride\library\orm\query\parser;

use ride\library\database\result\DatabaseResult;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\exception\ModelException;
use ride\library\orm\model\LocalizedModel;
use ride\library\orm\model\Model;
use ride\library\orm\OrmManager;


/**
 * Parser to parse database results into orm results
 */
class ResultParser {

    /**
     * Meta definition of the model of the result
     * @var \ride\library\orm\model\ModelMeta
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
     * @param \ride\library\orm\model\Model $model Model of this result
     * @return null
     */
    public function __construct(Model $model) {
        $this->model = $model;
    }

    /**
     * Parses a database result into a orm result
     * @param \ride\library\database\result\DatabaseResult $databaseResult
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
        $properties = array();

        foreach ($row as $column => $value) {
            $positionAliasSeparator = strpos($column, QueryParser::ALIAS_SEPARATOR);
            if ($positionAliasSeparator === false) {
                try {
                    if ($column != 'isDataLocalized' && $value !== null) {
                        $type = $this->meta->getField($column)->getType();
                        if ($type == 'serialize') {
                            $value = unserialize($value);
                        } elseif ($type == 'boolean') {
                            $value = (boolean) $value;
                        }
                    }
                } catch (ModelException $exception) {

                }

                $properties[$column] = $value;

                continue;
            }

            $alias = substr($column, 0, $positionAliasSeparator);
            $fieldName = substr($column, $positionAliasSeparator + QueryParser::ALIAS_SEPARATOR_LENGTH);

            if ($alias == QueryParser::ALIAS_SELF) {
                if ($fieldName !== LocalizedModel::FIELD_LOCALE && $value !== null) {
                    $type = $this->meta->getField($fieldName)->getType();
                    if ($type == 'serialize') {
                        $value = unserialize($value);
                    } elseif ($type == 'boolean') {
                        $value = (boolean) $value;
                    }
                }

                $properties[$fieldName] = $value;

                continue;
            }

            if (!isset($aliasses[$alias])) {
                $aliasses[$alias] = array();
            }

            $aliasses[$alias][$fieldName] = $value;
        }

        foreach ($aliasses as $fieldName => $value) {
            if (!array_key_exists(ModelTable::PRIMARY_KEY, $value)) {
                $properties[$fieldName] = $value;

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
                    $type = $relationProperty->getType();
                    if ($type == 'serialize' && isset($value[$relationPropertyName])) {
                        $value[$relationPropertyName] = unserialize($value[$relationPropertyName]);
                    }
                    if ($type == 'boolean' && $value[$relationPropertyName] !== null) {
                        $value[$relationPropertyName] = (boolean) $value[$relationPropertyName];
                    }
                }

                $properties[$fieldName] = $relationModel->createData($value);
                $properties[$fieldName]->_state = $value;
            } else {
                $properties[$fieldName] = null;
            }
        }

        $data = $this->model->createData($properties);
        $data->_state = $properties;

        return $data;
    }

}
