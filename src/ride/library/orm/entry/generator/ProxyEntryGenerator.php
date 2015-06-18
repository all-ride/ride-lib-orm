<?php

namespace ride\library\orm\entry\generator;

use ride\library\generator\Code;
use ride\library\generator\CodeClass;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\HasOneField;
use ride\library\orm\definition\field\HasField;
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\loader\ModelRegister;
use ride\library\orm\meta\ModelMeta;
use ride\library\system\file\File;

/**
 * Entry generator for the proxy entry class
 */
class ProxyEntryGenerator extends AbstractEntryGenerator {

    /**
     * Generates an entry class for the provided model
     * @param \ride\library\orm\loader\ModelRegister $modelRegister Instance of
     * the model register
     * @param string $modelName Name of the model to generate an entry for
     * @param \ride\library\system\file\File $sourcePath Path of the source
     * directory where the class will be generated
     * @return null
     */
    public function generate(ModelRegister $modelRegister, $modelName, File $sourcePath) {
        $model = $modelRegister->getModel($modelName);
        $meta = $model->getMeta();

        $entryClassName = $meta->getEntryClassName();
        if ($entryClassName == ModelMeta::CLASS_ENTRY) {
            $entryClassName = $this->defaultNamespace . '\\' . $modelName . 'Entry';
        }

        $proxyClassName = $this->defaultNamespace . '\\proxy\\' . $modelName . 'EntryProxy';

        $class = $this->generator->createClass($proxyClassName, $entryClassName, array('ride\\library\\orm\\entry\\EntryProxy'));
        $class->setDescription("Generated proxy for an entry of the " . $modelName . " model\n\nNOTE: Do not edit this class");

        $isLocalized = $meta->isLocalized();
        $fields = $meta->getFields();

        $this->generateProxy($class, $fields, $modelName, $isLocalized);

        foreach ($fields as $field) {
            if ($field instanceof PropertyField || $field instanceof BelongsToField) {
                $this->generateProperty($class, $field, $modelRegister);
            } elseif ($field instanceof HasOneField || $field instanceof HasManyField) {
                $this->generateHas($class, $field, $modelRegister);
            }
        }

        if ($isLocalized) {
            $this->generateLocalized($class);
        }

        $classFileName = str_replace('\\', '/', $proxyClassName) . '.php';

        $sourceFile = $sourcePath->getChild($classFileName);
        $sourceFile->write($this->generator->generateClass($class));
    }

    protected function generateProxy(CodeClass $class, array $fields, $modelName, $isLocalized) {
        // constructor
        $constructorCode =
'$this->_model = $model;
$this->loadedValues = array();
$this->loadedFields = array();
$this->id = $id;

if ($id) {
    $this->entryState = self::STATE_CLEAN;
} else {
    $this->entryState = self::STATE_NEW;
}

$this->setLoadedValues($properties);';

        $modelProperty = $this->generator->createProperty('_model', 'ride\\library\\orm\\model\\Model', Code::SCOPE_PROTECTED);
        $modelProperty->setDescription('Instance of the ' . $modelName . ' model');

        $entryStateProperty = $this->generator->createProperty('entryState', 'integer', Code::SCOPE_PRIVATE);
        $entryStateProperty->setDescription('Array with the name of the field as key and the state as value');

        $loadedFieldsProperty = $this->generator->createProperty('loadedFields', 'array', Code::SCOPE_PRIVATE);
        $loadedFieldsProperty->setDescription('Array with the load status of the fields');

        $loadedValuesProperty = $this->generator->createProperty('loadedValues', 'array', Code::SCOPE_PRIVATE);
        $loadedValuesProperty->setDescription('Array with the loaded values of the fields');

        $modelArgument = $this->generator->createVariable('model', 'ride\\library\\orm\\model\\Model');
        $modelArgument->setDescription('Instance of the ' . $modelName . ' model');

        $idArgument = $this->generator->createVariable('id', 'integer');
        $idArgument->setDescription('Id of the entry');

        $propertiesArgument = $this->generator->createVariable('properties', 'array');
        $propertiesArgument->setDescription('Values of the known properties');
        $propertiesArgument->setDefaultValue(array());

        $constructorArguments = array(
            $modelArgument,
            $idArgument,
            $propertiesArgument,
        );

        $constructorMethod = $this->generator->createMethod('__construct', $constructorArguments, $constructorCode);
        $constructorMethod->setDescription('Construct a new ' . $modelName . ' entry proxy');

        // unlink
        $unlinkCode =

'if (isset($this->checkUnlink)) {
    return;
}

$this->_model = null;
$this->checkUnlink = true;

foreach ($this->loadedFields as $property => $loadState) {
    if ($this->$property instanceof EntryProxy) {
        $this->$property->unlink();
    }
    if (isset($this->loadedValues[$property])) {
        if ($this->loadedValues[$property] instanceof EntryProxy) {
            $this->loadedValues[$property]->unlink();
        } elseif (is_array($this->loadedValues[$property])) {
            foreach ($this->loadedValues[$property] as $key => $value) {
                if ($value instanceof EntryProxy) {
                    $value->unlink();
                }
            }
        }
    }

    if (is_array($this->$property)) {
        foreach ($this->$property as $key => $value) {
            if ($value instanceof EntryProxy) {
                $value->unlink();
            }
        }
    }
}

unset($this->checkUnlink);';

        $unlink = $this->generator->createMethod('unlink', array(), $unlinkCode);
        $unlink->setDescription('Removes the link with the ORM');


        $valueArgument = $this->generator->createVariable('value', 'mixed');
        $valueArgument->setDescription('Value to process');

        $processLoadedValueCode =
'if (is_object($value)) {
    return clone $value;
} elseif (is_array($value)) {
    foreach ($value as $i => $iValue) {
        $value[$i] = $this->processLoadedValue($iValue);
    }
}

return $value;';

        $processLoadedValueMethod = $this->generator->createMethod('processLoadedValue', array($valueArgument), $processLoadedValueCode, Code::SCOPE_PROTECTED);
        $processLoadedValueMethod->setDescription('Processes the value for the state, clones all instances');

        $fieldNameArgument = $this->generator->createVariable('fieldName', 'string|array');
        $fieldNameArgument->setDescription('Field name of the provided value or an array with values of multiple fields');

        $valueArgument = $this->generator->createVariable('value', 'mixed');
        $valueArgument->setDescription('Value of the provided field');
        $valueArgument->setDefaultValue(null);

        $setLoadedValuesCode =
'if (!is_array($fieldName)) {
    $values = array($fieldName => $value);
} else {
    $values = $fieldName;

    $this->loadedValues = array();
}

foreach ($values as $fieldName => $value) {
    if (!isset($this->loadedValues[$fieldName])) {
        $this->$fieldName = $value;
    }

    $this->loadedValues[$fieldName] = $this->processLoadedValue($value);
    $this->loadedFields[$fieldName] = true;
}';

        $setLoadedValuesMethod = $this->generator->createMethod('setLoadedValues', array($fieldNameArgument, $valueArgument), $setLoadedValuesCode);
        $setLoadedValuesMethod->setDescription('Sets the loaded value of the data source');

        $fieldNameArgument = $this->generator->createVariable('fieldName', 'string');
        $fieldNameArgument->setDescription('Name of the field');
        $fieldNameArgument->setDefaultValue(null);

        $getLoadedValuesCode =
'if (!$fieldName) {
    return $this->loadedValues;
} elseif (isset($this->loadedValues[$fieldName])) {
    return $this->loadedValues[$fieldName];
} else {
    return null;
}';

        $getLoadedValuesMethod = $this->generator->createMethod('getLoadedValues', array($fieldNameArgument), $getLoadedValuesCode);
        $getLoadedValuesMethod->setDescription('Sets the loaded value of the data source');

        $fieldNameArgument = $this->generator->createVariable('fieldName', 'string');
        $fieldNameArgument->setDescription('Name of the field');

        $isValueLoadedMethod = $this->generator->createMethod('isValueLoaded', array($fieldNameArgument), 'return array_key_exists($fieldName, $this->loadedValues);');
        $isValueLoadedMethod->setDescription('Checks if a the value of a field is loaded');
        $isValueLoadedMethod->setReturnValue($this->generator->createVariable('result', 'boolean'));

        $isFieldSetMethod = $this->generator->createMethod('isFieldSet', array($fieldNameArgument), 'return isset($this->loadedFields[$fieldName]);');
        $isFieldSetMethod->setDescription('Checks if a field is set');
        $isFieldSetMethod->setReturnValue($this->generator->createVariable('result', 'boolean'));

        // property loader
        $loadPropertiesCode = '
$id = $this->getId();
if (!$id) {
    return;
}

';
        if ($isLocalized) {
            $loadPropertiesCode .=
'$query = $this->_model->createQuery(parent::getLocale());
$query->setFetchUnlocalized(true);';
        } else {
            $loadPropertiesCode .= '$query = $this->_model->createQuery();';
        }

        $loadPropertiesCode .= '
$query->setRecursiveDepth(0);
$query->addCondition(\'{' . ModelTable::PRIMARY_KEY . '} = %1%\', $id);
$entry = $query->queryFirst();

if (!$entry) {
';

        foreach ($fields as $field) {
            $fieldName = $field->getName();
            if ($field instanceof HasField || $fieldName == ModelTable::PRIMARY_KEY) {
                continue;
            }

            $loadPropertiesCode .= '    $this->loadedFields[\'' . $fieldName . '\'] = true;' . "\n";
        }

        if ($isLocalized) {
            $loadPropertiesCode .= '    $this->loadedFields[\'locale\'] = true;' . "\n";
        }

        $loadPropertiesCode .= '
    $this->entryState = self::STATE_NEW;

    return;
}

';
        foreach ($fields as $field) {
            $fieldName = $field->getName();
            if ($field instanceof HasField || $fieldName == ModelTable::PRIMARY_KEY) {
                continue;
            }

            if ($field->getType() == 'boolean' && substr($fieldName, 0, 2) == 'is') {
                $getterMethodName = $fieldName;
            } else {
                $getterMethodName = 'get' . ucfirst($fieldName);
            }

            $loadPropertiesCode .=
'if (!isset($this->loadedFields[\'' . $fieldName . '\'])) {
    $this->' . $fieldName . ' = $entry->' . $getterMethodName . '();
    $this->loadedValues[\'' . $fieldName . '\'] = $entry->loadedValues[\'' . $fieldName . '\'];
    $this->loadedFields[\'' . $fieldName . '\'] = true;
}
';
        }

        if ($isLocalized) {
            $loadPropertiesCode .=
'
$this->setLocale($entry->getLocale());
$this->setIslocalized($entry->isLocalized());
$this->loadedFields[\'locale\'] = true;
';
        }

        $loadPropertiesMethod = $this->generator->createMethod('loadProperties', array(), trim($loadPropertiesCode), Code::SCOPE_PRIVATE);
        $loadPropertiesMethod->setDescription('Loads the values of the properties of this ' . $modelName . ' entry');

        // relation loader
        $fieldNameArgument = $this->generator->createVariable('fieldName', 'string');
        $fieldNameArgument->setDescription('Name of the relation field');

        $loadRelationCode =
'$id = $this->getId();
if (!$id) {
    return;
}

';

        if ($isLocalized) {
            $loadRelationCode .=
'$query = $this->_model->createQuery($this->getLocale());
$query->setFetchUnlocalized(true);';
        } else {
            $loadRelationCode .= '$query = $this->_model->createQuery();';
        }

        $loadRelationCode .= '
$entry = $query->queryRelation($this->getId(), $fieldName);

$getterMethodName = \'get\' . ucfirst($fieldName);
$this->$fieldName = $entry->$getterMethodName();
$this->loadedValues[$fieldName] = $entry->loadedValues[$fieldName];
$this->loadedFields[$fieldName] = true;';

        $loadRelationMethod = $this->generator->createMethod('loadRelation', array($fieldNameArgument), trim($loadRelationCode), Code::SCOPE_PRIVATE);
        $loadRelationMethod->setDescription('Loads the value of a relation field of this ' . $modelName . ' entry');

        // add everything to the class
        $class->addProperty($modelProperty);
        $class->addProperty($loadedValuesProperty);
        $class->addProperty($loadedFieldsProperty);
        $class->addMethod($constructorMethod);
        $class->addMethod($unlink);
        $class->addMethod($processLoadedValueMethod);
        $class->addMethod($setLoadedValuesMethod);
        $class->addMethod($getLoadedValuesMethod);
        $class->addMethod($isValueLoadedMethod);
        $class->addMethod($isFieldSetMethod);
        $class->addMethod($loadPropertiesMethod);
        $class->addMethod($loadRelationMethod);
    }

    protected function generateProperty(CodeClass $class, ModelField $field, ModelRegister $modelRegister) {
        $name = $field->getName();
        if ($name == ModelTable::PRIMARY_KEY) {
            return;
        }
        $ucName = ucfirst($name);

        if ($field instanceof PropertyField) {
            $isProperty = true;
            $type = $this->normalizeType($field->getType());
        } else {
            $isProperty = false;
            $relationModelName = $field->getRelationModelName();
            $relationModel = $modelRegister->getModel($relationModelName);
            $relationModelMeta = $relationModel->getMeta();

            $type = $relationModelMeta->getEntryClassName();
        }

        $description = $field->getOption('description');

        $defaultValue = $field->getDefaultValue();

        $property = $this->generator->createProperty($name, $type, Code::SCOPE_PROTECTED);
        $property->setDescription($description);
        if (isset($relationModelName)) {
            $property->setDefaultValue(null);
        } elseif ($defaultValue !== null) {
            $property->setDefaultValue($defaultValue);
        }

        if ($type == 'boolean' && substr($name, 0, 2) == 'is') {
            $getterMethodName = $name;
        } else {
            $getterMethodName = 'get' . $ucName;
        }

        $setterCode =
'if (!isset($this->loadedFields[\'' . $name  . '\'])) {
    $this->loadProperties();
}

$oldValue = null;
if (array_key_exists(\'' . $name . '\', $this->loadedValues)) {
    $oldValue = $this->loadedValues[\'' . $name . '\'];
}
';

        if ($isProperty) {
            $setterCode .= '
if ($oldValue === $' . $name . ')  {
    $this->' . $name . ' = $' . $name . ';

    return;
}';
        } else {
            $setterCode .= '
if ((!$oldValue && !$' . $name . ') || ($oldValue && $' . $name . ' && $oldValue->getId() === $' . $name . '->getId()))  {
    $this->' . $name . ' = $' . $name . ';

    return;
}';
        }

        $setterCode .= '

return parent::set' . $ucName . '($' . $name . ');';

        $getterCode =
'if (!isset($this->loadedFields[\'' . $name  . '\'])) {
    $this->loadProperties();
}

return parent::' . $getterMethodName . '();';

        $setter = $this->generator->createMethod('set' . $ucName, array($property), $setterCode);
        $getter = $this->generator->createMethod($getterMethodName, array(), $getterCode);
        $getter->setReturnValue($property);

        if ($description) {
            $description{0} = strtolower($description{0});
            $setter->setDescription('Sets the ' . $description);
            $getter->setDescription('Gets the ' . $description);
        }

        $class->addMethod($setter);
        $class->addMethod($getter);
    }

    protected function generateHas(CodeClass $class, RelationField $field, ModelRegister $modelRegister) {
        $name = $field->getName();
        $ucName = ucfirst($name);

        $relationModelName = $field->getRelationModelName();
        $relationModel = $modelRegister->getModel($relationModelName);
        $relationModelMeta = $relationModel->getMeta();

        if ($field instanceof HasManyField) {
            $type = 'array';
        } else {
            $type = $relationModelMeta->getEntryClassName();
        }

        $description = $field->getOption('description');
        $defaultValue = $field->getDefaultValue();

        $property = $this->generator->createProperty($name, $type, Code::SCOPE_PROTECTED);
        $property->setDescription($description);
        if ($type == 'array') {
            $property->setDefaultValue(array());
        }

        $setterCode =
'if (!isset($this->loadedFields[\'' . $name  . '\'])) {
    $this->loadRelation(\'' . $name . '\');
}

return parent::set' . $ucName . '($' . $name . ');';

        $getterCode =
'if (!isset($this->loadedFields[\'' . $name  . '\'])) {
    $this->loadRelation(\'' . $name . '\');
}

return parent::get' . $ucName . '();';

        $setter = $this->generator->createMethod('set' . $ucName, array($property), $setterCode);
        $getter = $this->generator->createMethod('get' . $ucName, array(), $getterCode);
        $getter->setReturnValue($property);

        if ($description) {
            $description{0} = strtolower($description{0});
            $setter->setDescription('Sets the ' . $description);
            $getter->setDescription('Gets the ' . $description);
        }

        $class->addMethod($setter);
        $class->addMethod($getter);
    }

    protected function generateLocalized($class) {
        // get locale
        $return = $this->generator->createVariable('locale', 'string');
        $return->setDescription('Code of the locale');

        $getterCode =
'if (!isset($this->loadedFields[\'locale\'])) {
    $this->loadProperties();
}

return parent::getLocale();';

        $getter = $this->generator->createMethod('getLocale', array(), $getterCode);
        $getter->setReturnValue($return);
        $getter->setDescription('Gets the locale of this entry');

        $class->addMethod($getter);

        // is localized
        $return = $this->generator->createVariable('isLocalized', 'boolean');
        $return->setDescription('Flag to see if the entry is localized in the requested locale');

        $getterCode =
'if (!isset($this->loadedFields[\'locale\'])) {
    $this->loadProperties();
}

return parent::isLocalized();';

        $getter = $this->generator->createMethod('isLocalized', array(), $getterCode);
        $getter->setReturnValue($return);
        $getter->setDescription('Gets whether the entry is localized in the requested locale');

        $class->addMethod($getter);
    }

}
