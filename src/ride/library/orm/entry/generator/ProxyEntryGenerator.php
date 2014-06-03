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

        $class = $this->generator->createClass($proxyClassName, $entryClassName, array('ride\\library\\orm\\entry\\proxy\\EntryProxy'));
        $class->setDescription("Generated proxy for an entry of the " . $modelName . " model\n\nNOTE: Do not edit this class");

        $fields = $meta->getFields();
        $this->generateProxy($class, $fields, $modelName, $meta->isLocalized());
        foreach ($fields as $field) {
            if ($field instanceof PropertyField || $field instanceof BelongsToField) {
                $this->generateProperty($class, $field, $modelRegister);
            } elseif ($field instanceof HasOneField || $field instanceof HasManyField) {
                $this->generateHas($class, $field, $modelRegister);
            }
        }

        $classFileName = str_replace('\\', '/', $proxyClassName) . '.php';

        $sourceFile = $sourcePath->getChild($classFileName);
        $sourceFile->write($this->generator->generateClass($class));
    }

    protected function generateProxy(CodeClass $class, array $fields, $modelName, $isLocalized) {
        // constructor
        $constructorCode =
'$this->id = $id;
$this->_model = $model;
$this->_isClean = true;
$this->_state = array(\'id\' => $id);
$this->_loaded = array();

foreach ($properties as $propertyName => $propertyValue) {
    $this->$propertyName = $propertyValue;
    $this->_state[$propertyName] = $propertyValue;
    $this->_loaded[$propertyName] = true;
}';

        $modelProperty = $this->generator->createProperty('_model', 'ride\\library\\orm\\model\\Model', Code::SCOPE_PRIVATE);
        $modelProperty->setDescription('Instance of the ' . $modelName . ' model');

        $cleanProperty = $this->generator->createProperty('_isClean', 'boolean', Code::SCOPE_PRIVATE);
        $cleanProperty->setDescription('Flag to see if this entry is clean and not modified');

        $stateProperty = $this->generator->createProperty('_state', 'array', Code::SCOPE_PRIVATE);
        $stateProperty->setDescription('Array with the name of the field as key and the state as value');

        $loadProperty = $this->generator->createProperty('_loaded', 'array', Code::SCOPE_PRIVATE);
        $loadProperty->setDescription('Array with the load state of the entry');

        $modelArgument = $this->generator->createVariable('model', 'ride\\library\\orm\\model\\Model');
        $modelArgument->setDescription('Instance of the ' . $modelName . ' model');

        $idArgument = $this->generator->createVariable('id', 'integer');
        $idArgument->setDescription('Id of the entry');

        $propertiesArgument = $this->generator->createVariable('properties', 'array');
        $propertiesArgument->setDescription('Values of the known properties');
        $propertiesArgument->setDefaultValue(array());

        $arguments = array(
            $modelArgument,
            $idArgument,
            $propertiesArgument,
        );

        $constructor = $this->generator->createMethod('__construct', $arguments, $constructorCode);
        $constructor->setDescription('Construct a new ' . $modelName . ' entry proxy');

        // entry state
        $fieldStateGetterCode =
'if (!$this->hasFieldState($fieldName)) {
    return null;
}

return $this->_state[$fieldName];';

        $stateArgument = $this->generator->createVariable('state', 'array');
        $stateArgument->setDescription('Array with the name of the field as key and the state as value');

        $cleanChecker = $this->generator->createMethod('hasCleanState', array(), 'return $this->_isClean;');
        $cleanChecker->setDescription('Gets whether this entry is clean and not modified');

        $entryStateSetter = $this->generator->createMethod('setEntryState', array($stateArgument), '$this->_state = $state;');
        $entryStateSetter->setDescription('Sets the state of this entry');
        $entryStateGetter = $this->generator->createMethod('getEntryState', array(), 'return $this->_state;');
        $entryStateGetter->setDescription('Gets the state of this entry');
        $entryStateGetter->setReturnValue($stateArgument);

        $fieldArgument = $this->generator->createVariable('fieldName', 'string');
        $fieldArgument->setDescription('Name of the field');

        $valueArgument = $this->generator->createVariable('value', 'mixed');
        $valueArgument->setDescription('State value of the provided field');

        $arguments = array(
            $fieldArgument,
            $valueArgument,
        );
        $fieldStateSetter = $this->generator->createMethod('setFieldState', $arguments, '$this->_state[$fieldName] = $value;');
        $fieldStateSetter->setDescription('Sets the state of a field of this entry');
        $fieldStateChecker = $this->generator->createMethod('hasFieldState', array($fieldArgument), 'return isset($this->_state[$fieldName]);');
        $fieldStateChecker->setDescription('Checks if the state of a field is set');
        $fieldStateChecker->setReturnValue($this->generator->createVariable('result', 'boolean'));
        $fieldStateGetter = $this->generator->createMethod('getFieldState', array($fieldArgument), $fieldStateGetterCode);
        $fieldStateGetter->setDescription('Gets the state of a field of this entry');
        $fieldStateGetter->setReturnValue($valueArgument);

        $fieldLoadedChecker = $this->generator->createMethod('isFieldLoaded', array($fieldArgument), 'return isset($this->_loaded[$fieldName]);');
        $fieldLoadedChecker->setDescription('Checks if a field has been loaded');
        $fieldLoadedChecker->setReturnValue($this->generator->createVariable('result', 'boolean'));

        // property loader
        $propertyLoaderCode = '
$id = $this->getId();
if (!$id) {
    return;
}

$this->_isClean = false;

';
        if ($isLocalized) {
            $propertyLoaderCode .=
'$query = $this->_model->createQuery($this->getLocale());
$query->setFetchUnlocalized(true);';
        } else {
            $propertyLoaderCode .= '$query = $this->_model->createQuery();';
        }

        $propertyLoaderCode .= '
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

            $propertyLoaderCode .= '    $this->_loaded[\'' . $fieldName . '\'] = true;' . "\n";
        }

        $propertyLoaderCode .= '
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

            $propertyLoaderCode .=
'if (!isset($this->_loaded[\'' . $fieldName . '\'])) {
    $this->' . $fieldName . ' = $entry->' . $getterMethodName . '();
    $this->_state[\'' . $fieldName . '\'] = $entry->_state[\'' . $fieldName . '\'];
    $this->_loaded[\'' . $fieldName . '\'] = true;
}
';
        }

        $propertyLoader = $this->generator->createMethod('loadProperties', array(), trim($propertyLoaderCode), Code::SCOPE_PRIVATE);
        $propertyLoader->setDescription('Loads the values of the properties of this ' . $modelName . ' entry');        // property loader

        // relation loader
        $fieldArgument = $this->generator->createVariable('field', 'string');
        $fieldArgument->setDescription('Name of the relation field');

$relationLoaderCode =
'$id = $this->getId();
if (!$id) {
    return;
}

$this->_isClean = false;

';

        if ($isLocalized) {
            $relationLoaderCode .=
'$query = $this->_model->createQuery($this->getLocale());
$query->setFetchUnlocalized(true);';

        } else {
            $relationLoaderCode .= '$query = $this->_model->createQuery();';
        }

        $relationLoaderCode .= '
$entry = $query->queryRelation($this->getId(), $field);

$getterMethodName = \'get\' . ucfirst($field);
$this->$field = $entry->$getterMethodName();
$this->_state[$field] = $entry->_state[$field];
$this->_loaded[$field] = true;
';

        $relationLoader = $this->generator->createMethod('loadRelation', array($fieldArgument), trim($relationLoaderCode), Code::SCOPE_PRIVATE);
        $relationLoader->setDescription('Loads the value of a relation field of this ' . $modelName . ' entry');

        // add everything to the class
        $class->addProperty($modelProperty);
        $class->addProperty($cleanProperty);
        $class->addProperty($stateProperty);
        $class->addProperty($loadProperty);
        $class->addMethod($constructor);
        $class->addMethod($cleanChecker);
        $class->addMethod($entryStateSetter);
        $class->addMethod($entryStateGetter);
        $class->addMethod($fieldStateSetter);
        $class->addMethod($fieldStateChecker);
        $class->addMethod($fieldStateGetter);
        $class->addMethod($fieldLoadedChecker);
        $class->addMethod($propertyLoader);
        $class->addMethod($relationLoader);
    }

    protected function generateProperty(CodeClass $class, ModelField $field, ModelRegister $modelRegister) {
        $name = $field->getName();
        if ($name == ModelTable::PRIMARY_KEY) {
            return;
        }
        $ucName = ucfirst($name);

        if ($field instanceof PropertyField) {
            $type = $this->normalizeType($field->getType());
        } else {
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
'$this->_isClean = false;
$this->_loaded[\'' . $name  . '\'] = true;

return parent::set' . $ucName . '($' . $name . ');';

        $getterCode =
'if (!isset($this->_loaded[\'' . $name  . '\'])) {
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
'$this->_isClean = false;
$this->_loaded[\'' . $name  . '\'] = true;

return parent::set' . $ucName . '($' . $name . ');';

        $getterCode =
'if (!isset($this->_loaded[\'' . $name  . '\'])) {
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

}
