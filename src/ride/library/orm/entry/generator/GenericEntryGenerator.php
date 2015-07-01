<?php

namespace ride\library\orm\entry\generator;

use ride\library\generator\Code;
use ride\library\generator\CodeClass;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\HasOneField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\loader\ModelRegister;
use ride\library\orm\meta\ModelMeta;
use ride\library\orm\model\Model;
use ride\library\reflection\Boolean;
use ride\library\system\file\File;

/**
 * Entry generator for the generic entry class
 */
class GenericEntryGenerator extends AbstractEntryGenerator {

    /**
     * Array with the behaviour initializers to hook into the entry generation
     * @var array
     */
    protected $behaviourInitializers = array();

    /**
     * Sets the behaviour initializers to hook into the entry generation
     * @param $behaviourInitializers
     * @return null
     */
    public function setBehaviourInitializers(array $behaviourInitializers) {
        $this->behaviourInitializers = $behaviourInitializers;
    }

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

        $entryClassName = $this->defaultNamespace . '\\' . $modelName . 'Entry';

        $class = $this->generator->createClass($entryClassName, ModelMeta::CLASS_ENTRY);
        $class->setDescription("Generated entry for the " . $modelName . " model\n\nNOTE: Do not edit this class directly, define your own and extend from this one.");
        $class->addMethod($this->generator->createMethod('__toString', array(), 'return \'' . $modelName . ' #\' . $this->getId();'));

        $fields = $meta->getFields();

        $this->generateState($class, $fields);

        foreach ($fields as $field) {
            if ($field instanceof PropertyField) {
                $this->generateProperty($class, $field);
            } elseif ($field instanceof BelongsToField || $field instanceof HasOneField) {
                $this->generateHas($class, $field, $modelRegister);
            } elseif ($field instanceof HasManyField) {
                $this->generateHasMany($class, $field, $modelRegister);
            }
        }

        $modelTable = $meta->getModelTable();
        foreach ($this->behaviourInitializers as $behaviourInitializer) {
            $behaviourInitializer->generateEntryClass($modelTable, $this->generator, $class);
        }

        if ($meta->isLocalized()) {
            $this->generateLocalized($class);
        }

        $sourceFile = $sourcePath->getChild(str_replace('\\', '/', $entryClassName) . '.php');
        $sourceFile->write($this->generator->generateClass($class));
    }

    protected function generateState(CodeClass $class, array $fields) {
        // entry state
        $getEntryStateCode =
'$entryState = parent::getEntryState();
if ($entryState !== self::STATE_CLEAN || isset($this->checkEntryState)) {
    return $entryState;
}

$this->checkEntryState = true;
$entryState = null;
';
        foreach ($fields as $fieldName => $field) {
            if ($field instanceof BelongsToField || $field instanceof HasOneField) {
                $getEntryStateCode .=
'if (!$entryState && $this->' . $fieldName . ' && $this->' . $fieldName . '->getEntryState() !== self::STATE_CLEAN) {
    $entryState = self::STATE_DIRTY;
}

';
            } elseif ($field instanceof HasManyField) {
                $getEntryStateCode .=
'if (!$entryState && $this->' . $fieldName . ') {
    foreach ($this->' . $fieldName . ' as $value) {
        if ($value->getEntryState() !== self::STATE_CLEAN) {
            $entryState = self::STATE_DIRTY;

            break;
        }
    }
}

';
            }
        }

        $getEntryStateCode .= '
if (!$entryState) {
    $entryState = self::STATE_CLEAN;
}

unset($this->checkEntryState);

return $entryState;';

        $getEntryStateMethod = $this->generator->createMethod('getEntryState', array(), $getEntryStateCode);
        $getEntryStateMethod->setDescription('Gets the state of the entry');

        $class->addMethod($getEntryStateMethod);
    }

    protected function generateProperty(CodeClass $class, PropertyField $field) {
        $name = $field->getName();
        if ($name == ModelTable::PRIMARY_KEY) {
            return;
        }

        $type = $this->normalizeType($field->getType());
        $description = $field->getOption('description');

        $defaultValue = $field->getDefaultValue();

        $property = $this->generator->createProperty($name, $type, Code::SCOPE_PROTECTED);
        $property->setDescription($description);
        if ($defaultValue !== null) {
            $property->setDefaultValue($defaultValue);
        }

        $setterCode =
'if ($this->' . $name . ' === $' . $name . ') {
    return;
}

$this->' . $name . ' = $' . $name . ';

if ($this->entryState === self::STATE_CLEAN) {
    $this->entryState = self::STATE_DIRTY;
}';

        $setter = $this->generator->createMethod('set' . ucfirst($name), array($property), $setterCode);
        if ($type == 'boolean' && substr($name, 0, 2) == 'is') {
            $getter = $this->generator->createMethod($name, array(), 'return $this->' . $name . ';');
        } else {
            $getter = $this->generator->createMethod('get' . ucfirst($name), array(), 'return $this->' . $name . ';');
        }
        $getter->setReturnValue($property);

        if ($description) {
            $description{0} = strtolower($description{0});
            $setter->setDescription('Sets the ' . $description);
            $getter->setDescription('Gets the ' . $description);
        }

        $class->addProperty($property);
        $class->addMethod($setter);
        $class->addMethod($getter);
    }

    protected function generateHas(CodeClass $class, RelationField $field, ModelRegister $modelRegister) {
        $relationModelName = $field->getRelationModelName();
        $relationModel = $modelRegister->getModel($relationModelName);
        $relationModelMeta = $relationModel->getMeta();

        $name = $field->getName();
        $type = $relationModelMeta->getEntryClassName();
        $description = $field->getOption('description');

        $typeNamespace = null;
        $typeName = null;
        Code::resolveClassName($type, $typeNamespace, $typeName);
        $typeAlias = 'Alias' . $typeName;

        $property = $this->generator->createProperty($name, $type, Code::SCOPE_PROTECTED);
        $property->setDefaultValue(null);
        $property->setDescription($description);

        $setterCode =
'$isClean = false;
if ((!$this->' . $name . ' && !$' . $name . ') || ($this->' . $name . ' && $' . $name . ' && $this->' . $name . '->getId() === $' . $name . '->getId())) {
    $isClean = true;
}

$this->' . $name . ' = $' . $name . ';

if (!$isClean && $this->entryState === self::STATE_CLEAN) {
    $this->entryState = self::STATE_DIRTY;
}';

        $setter = $this->generator->createMethod('set' . ucfirst($name), array($property), $setterCode);
        $setter->addUse($type, $typeAlias);
        $getter = $this->generator->createMethod('get' . ucfirst($name), array(), 'return $this->' . $name . ';');
        $getter->setReturnValue($property);

        if ($description) {
            $description{0} = strtolower($description{0});
            $setter->setDescription('Sets the ' . $description);
            $getter->setDescription('Gets the ' . $description);
        }

        $class->addProperty($property);
        $class->addMethod($setter);
        $class->addMethod($getter);
    }

    protected function generateHasMany(CodeClass $class, HasManyField $field, ModelRegister $modelRegister) {
        $relationModelName = $field->getRelationModelName();
        $relationModel = $modelRegister->getModel($relationModelName);
        $relationModelMeta = $relationModel->getMeta();

        $name = $field->getName();
        $description = $field->getOption('description');

        $property = $this->generator->createProperty($name, 'array', Code::SCOPE_PROTECTED);
        $property->setDescription($description);
        $property->setDefaultValue(array());

        $type = $relationModelMeta->getEntryClassName();
        if ($type == ModelMeta::CLASS_ENTRY) {
            $type = $this->defaultNamespace . '\\' . $relationModelName . 'Entry';
        }

        $typeNamespace = null;
        $typeName = null;
        Code::resolveClassName($type, $typeNamespace, $typeName);
        $typeAlias = 'Alias' . $typeName;

        $adderCode =
'$this->get' . ucfirst($name) . '();

$this->' . $name . '[] = $entry;

if ($this->entryState === self::STATE_CLEAN) {
    $this->entryState = self::STATE_DIRTY;
}';

        $setterCode =
'foreach ($' . $name . ' as $' . $name . 'Index => $' . $name . 'Value) {
    if (!$' . $name . 'Value instanceof ' . $typeAlias . ') {
        throw new InvalidArgumentException("Could not set ' . $name . ': value on index $' . $name . 'Index is not an instance of ' . str_replace('\\', '\\\\', $type) . '");
    }
}

$this->' . $name . ' = $' . $name . ';

if ($this->entryState === self::STATE_CLEAN) {
    $this->entryState = self::STATE_DIRTY;
}';

        $removerCode =
'$this->get' . ucfirst($name) . '();

$status = false;

foreach ($this->' . $name . ' as $' . $name . 'Index => $' . $name . 'Value) {
    if ($' . $name . 'Value === $entry || $' . $name . 'Value->getId() === $entry->getId()) {
        unset($this->' . $name . '[$' . $name . 'Index]);

        $status = true;

        break;
    }
}

if ($status && $this->entryState === self::STATE_CLEAN) {
    $this->entryState = self::STATE_DIRTY;
}

return $status;';

        $argument = $this->generator->createVariable('entry', $type);

        $adder = $this->generator->createMethod('addTo' . ucfirst($name), array($argument), $adderCode);
        $remover = $this->generator->createMethod('removeFrom' . ucfirst($name), array($argument), $removerCode);
        $setter = $this->generator->createMethod('set' . ucfirst($name), array($property), $setterCode);
        $setter->addUse('InvalidArgumentException');
        $setter->addUse($type, $typeAlias);
        $getter = $this->generator->createMethod('get' . ucfirst($name), array(), 'return $this->' . $name . ';');
        $getter->setReturnValue($property);

        if ($description) {
            $description{0} = strtolower($description{0});
            $adder->setDescription('Adds an entry to the ' . $description);
            $remover->setDescription('Removes an entry from the ' . $description);
            $setter->setDescription('Sets the ' . $description);
            $getter->setDescription('Gets the ' . $description);
        }

        $class->addProperty($property);
        $class->addMethod($adder);
        $class->addMethod($remover);
        $class->addMethod($setter);
        $class->addMethod($getter);
    }

    protected function generateLocalized(CodeClass $class) {
        $localeProperty = $this->generator->createProperty('locale', 'string', Code::SCOPE_PROTECTED);
        $localeProperty->setDescription('Code of the locale');

        $setLocale =
'if ($locale != $this->locale && $this->entryState === self::STATE_CLEAN) {
    $this->entryState = self::STATE_DIRTY;
}

$this->locale = $locale;';

        $setter = $this->generator->createMethod('setLocale', array($localeProperty), $setLocale);
        $setter->setDescription('Sets the locale of the localized entry fields');
        $getter = $this->generator->createMethod('getLocale', array(), 'return $this->locale;');
        $getter->setDescription('Gets the locale of the localized entry fields');
        $getter->setReturnValue($localeProperty);

        $class->addProperty($localeProperty);
        $class->addMethod($setter);
        $class->addMethod($getter);

        $isLocalizedProperty = $this->generator->createProperty('isLocalized', 'boolean', Code::SCOPE_PROTECTED);
        $isLocalizedProperty->setDescription('Flag to see if the entry is localized');

        $setter = $this->generator->createMethod('setIsLocalized', array($isLocalizedProperty), '$this->isLocalized = $isLocalized;');
        $setter->setDescription('Sets whether the entry is localized in the requested locale');
        $getter = $this->generator->createMethod('isLocalized', array(), 'return $this->isLocalized;');
        $getter->setDescription('Gets whether the entry is localized in the requested locale');
        $getter->setReturnValue($isLocalizedProperty);

        $class->addProperty($isLocalizedProperty);
        $class->addMethod($setter);
        $class->addMethod($getter);

        $class->addImplements('ride\\library\\orm\\entry\\LocalizedEntry');
    }

}
