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
use ride\library\orm\model\behaviour\DateBehaviour;
use ride\library\orm\model\behaviour\SlugBehaviour;
use ride\library\orm\model\behaviour\VersionBehaviour;
use ride\library\orm\model\Model;
use ride\library\reflection\Boolean;
use ride\library\system\file\File;

use \InvalidArgumentException;

/**
 * Entry generator for the generic entry class
 */
class GenericEntryGenerator extends AbstractEntryGenerator {

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

        $fields = $meta->getFields();
        foreach ($fields as $field) {
            if ($field instanceof PropertyField) {
                $this->generateProperty($class, $field);
            } elseif ($field instanceof BelongsToField || $field instanceof HasOneField) {
                $this->generateHas($class, $field, $modelRegister);
            } elseif ($field instanceof HasManyField) {
                $this->generateHasMany($class, $field, $modelRegister);
            }
        }

        $behaviours = $model->getBehaviours();
        foreach ($behaviours as $behaviour) {
            if ($behaviour instanceof DateBehaviour) {
                $class->addImplements('ride\\library\\orm\\entry\\DatedEntry');

                $timestampArgument = $this->generator->createVariable('timestamp', 'integer');
                $timestampArgument->setDescription('UNIX timestamp of the date');

                $setterCode =
'if ($this->dateAdded) {
    return;
}

$this->dateAdded = $timestamp;
$this->dateModified = $timestamp;';

                $setterMethod = $this->generator->createMethod('setDateAdded', array($timestampArgument), $setterCode);
                $setterMethod->setDescription('Sets the add date');

                $class->addMethod($setterMethod);
            }
            if ($behaviour instanceof VersionBehaviour) {
                $class->addImplements('ride\\library\\orm\\entry\\VersionedEntry');
            }
            if ($behaviour instanceof SlugBehaviour) {
                $class->addImplements('ride\\library\\orm\\entry\\SluggedEntry');

                $slugValue = $meta->getOption('behaviour.slug');
                try {
                    Boolean::getBoolean($slugValue);
                } catch (InvalidArgumentException $exception) {
                    $fields = explode(',', $slugValue);

                    if (count($fields) == 1) {
                        $field = array_pop($fields);

                        $slugCode = 'return $this->get' . ucfirst($field) . '();';
                    } else {
                        $slugCode = "\$slug = '';\n";
                        foreach ($fields as $field) {
                            $slugCode .= '$slug .= \' \' . $this->get' . ucfirst(trim($field)) . "();\n";
                        }
                        $slugCode .= "\nreturn trim(\$slug);";
                    }

                    $slugMethod = $this->generator->createMethod('getSlugBase', array(), $slugCode);
                    $slugMethod->setDescription('Gets the desired slug based on properties of the entry');
                    $slugMethod->setReturnValue($this->generator->createVariable('result', 'string'));

                    $class->addMethod($slugMethod);
                }
            }
        }

        if ($meta->isLocalized()) {
            $this->generateLocalized($class);
        }

        $sourceFile = $sourcePath->getChild(str_replace('\\', '/', $entryClassName) . '.php');
        $sourceFile->write($this->generator->generateClass($class));
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

        $setter = $this->generator->createMethod('set' . ucfirst($name), array($property), '$this->' . $name . ' = $' . $name . ';');
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

        $property = $this->generator->createProperty($name, $type, Code::SCOPE_PROTECTED);
        $property->setDefaultValue(null);
        $property->setDescription($description);

        $setter = $this->generator->createMethod('set' . ucfirst($name), array($property), '$this->' . $name . ' = $' . $name . ';');
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

        $adderCode =
'$this->get' . ucfirst($name) . '();

$this->' . $name . '[$entry->getId()] = $entry;';

        $setterCode =
'foreach ($' . $name . ' as $' . $name . 'Index => $' . $name . 'Value) {
    if (!$' . $name . 'Value instanceof ' . $typeName . ') {
        throw new InvalidArgumentException("Could not set ' . $name . ': value on index $' . $name . 'Index is not an instance of ' . str_replace('\\', '\\\\', $type) . '");
    }
}

$this->' . $name . ' = $' . $name . ';';

        $removerCode =
'$this->get' . ucfirst($name) . '();

if (!isset($this->' . $name . '[$entry->getId()])) {
    return false;
}

unset($this->' . $name . '[$entry->getId()]);

return true;';

        $argument = $this->generator->createVariable('entry', $type);

        $adder = $this->generator->createMethod('addTo' . ucfirst($name), array($argument), $adderCode);
        $remover = $this->generator->createMethod('removeFrom' . ucfirst($name), array($argument), $removerCode);
        $setter = $this->generator->createMethod('set' . ucfirst($name), array($property), $setterCode);
        $setter->addUse('InvalidArgumentException');
        $setter->addUse($type);
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

        $setter = $this->generator->createMethod('setLocale', array($localeProperty), '$this->locale = $locale;');
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
