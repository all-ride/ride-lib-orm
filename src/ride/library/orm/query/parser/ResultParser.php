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
     * Instance of the model
     * @var \ride\library\orm\model\Model
     */
    protected $model;

    /**
     * Constructs a new result parser
     * @param \ride\library\orm\model\Model $model Model of this result
     * @return null
     */
    public function __construct(Model $model) {
        $this->model = $model;
    }

    /**
     * Parses a database result into a ORM result
     * @param \ride\library\database\result\DatabaseResult $databaseResult
     * @param string $indexFieldName Name of the field to index the result on
     * @return array Array with entry objects
     */
    public function parseResult(OrmManager $orm, DatabaseResult $databaseResult, $indexFieldName = null) {
        $this->orm = $orm;
        $this->meta = $this->model->getMeta();
        $this->isLocalized = $this->meta->isLocalized();
        $this->belongsTo = $this->meta->getBelongsTo();

        $result = array();

        if ($indexFieldName === null) {
            $indexFieldName = ModelTable::PRIMARY_KEY;
        }

        foreach ($databaseResult as $row) {
            $entry = $this->getEntryFromRow($row);

            if ($indexFieldName && isset($row[$indexFieldName])) {
                $result[$row[$indexFieldName]] = $entry;
            } elseif ($indexFieldName && isset($row[QueryParser::ALIAS_SELF . QueryParser::ALIAS_SEPARATOR . $indexFieldName])) {
                $result[$row[QueryParser::ALIAS_SELF . QueryParser::ALIAS_SEPARATOR . $indexFieldName]] = $entry;
            } else {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Gets an entry for the provided database result row
     * @param array $row Database result row
     * @return mixed Instance of an entry
     */
    protected function getEntryFromRow($row) {
        $aliasses = array();
        $properties = array();

        // extract aliasses and process values
        foreach ($row as $column => $value) {
            $positionAliasSeparator = strpos($column, QueryParser::ALIAS_SEPARATOR);
            if ($positionAliasSeparator === false) {
                try {
                    if ($column != QueryParser::ALIAS_IS_LOCALIZED && $value !== null) {
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

        $locale = null;
        if ($this->isLocalized && isset($properties[LocalizedModel::FIELD_LOCALE])) {
            $locale = $locale;
        }

        // handle relation entries
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

                $properties[$fieldName] = $relationModel->createProxy($value[ModelTable::PRIMARY_KEY], $locale, $value);
            } else {
                $properties[$fieldName] = null;
            }
        }

        // create entry
        if (!array_key_exists(ModelTable::PRIMARY_KEY, $properties)) {
            $properties[ModelTable::PRIMARY_KEY] = 0;
        }

        foreach ($this->belongsTo as $fieldName => $field) {
            if (!isset($properties[$fieldName]) || is_object($properties[$fieldName])) {
                continue;
            }

            $relationModel = $this->orm->getModel($field->getRelationModelName());

            $properties[$fieldName] = $relationModel->createProxy($properties[$fieldName], $locale);
        }

        $data = $this->model->createProxy($properties[ModelTable::PRIMARY_KEY], $locale, $properties);
        $data->setEntryState($this->processState($properties));

        return $data;
    }

    public function processState(array $data) {
        foreach ($data as $field => $fieldValue) {
            if (is_object($fieldValue)) {
                $data[$field] = clone $fieldValue;
            } elseif (is_array($fieldValue)) {
                $data[$field] = $this->processState($fieldValue);
            }
        }

        return $data;
    }

}