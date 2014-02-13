<?php

namespace pallo\library\orm\loader\io;

use pallo\library\database\definition\Index;
use pallo\library\orm\definition\field\BelongsToField;
use pallo\library\orm\definition\field\HasOneField;
use pallo\library\orm\definition\field\HasManyField;
use pallo\library\orm\definition\field\ModelField;
use pallo\library\orm\definition\field\PropertyField;
use pallo\library\orm\definition\field\RelationField;
use pallo\library\orm\definition\DataFormat;
use pallo\library\orm\definition\FieldValidator;
use pallo\library\orm\definition\ModelTable;
use pallo\library\orm\exception\OrmException;
use pallo\library\orm\model\behaviour\DatedBehaviour;
use pallo\library\orm\model\behaviour\LogBehaviour;
use pallo\library\orm\model\behaviour\UniqueBehaviour;
use pallo\library\orm\model\behaviour\VersionBehaviour;
use pallo\library\orm\model\meta\ModelMeta;
use pallo\library\orm\model\LocalizedModel;
use pallo\library\orm\model\Model;
use pallo\library\orm\OrmManager;
use pallo\library\reflection\Boolean;
use pallo\library\reflection\ReflectionHelper;
use pallo\library\system\file\File;
use pallo\library\validation\constraint\Constraint;
use pallo\library\validation\constraint\GenericConstraint;

use \DOMDocument;
use \DOMElement;
use pallo\library\orm\model\behaviour\FieldSlugBehaviour;

/**
 * Read and write model definitions from and to an xml structure
 */
abstract class AbstractXmlModelIO implements ModelIO {

    /**
     * Default value of a field type
     * @var string
     */
    const DEFAULT_FIELD_TYPE = 'string';

    /**
     * Default value of a field relation
     * @var string
     */
    const DEFAULT_FIELD_RELATION = 'hasMany';

    /**
     * Name of the belongsTo relation
     * @var string
     */
    const RELATION_BELONGS_TO = 'belongsTo';

    /**
     * Name of the hasOne relation
     * @var string
     */
    const RELATION_HAS_ONE = 'hasOne';

    /**
     * Name of the hasMany relation
     * @var string
     */
    const RELATION_HAS_MANY = 'hasMany';

    /**
     * Name of the xml root tag
     * @var string
     */
    const TAG_ROOT = 'models';

    /**
     * Name of the model tag
     * @var string
     */
    const TAG_MODEL = 'model';

    /**
     * Name of the field tag
     * @var string
     */
    const TAG_FIELD = 'field';

    /**
     * Name of the validation tag
     * @var string
     */
    const TAG_VALIDATION = 'validation';

    /**
     * Name of the parameter tag
     * @var string
     */
    const TAG_PARAMETER = 'parameter';

    /**
     * Name of the index tag
     * @var string
     */
    const TAG_INDEX = 'index';

    /**
     * Name of the index field tag
     * @var string
     */
    const TAG_INDEX_FIELD = 'indexField';

    /**
     * Name of the format tag
     * @var string
     */
    const TAG_FORMAT = 'format';

    /**
     * Name of the option tag
     * @var string
     */
    const TAG_OPTION = 'option';

    /**
     * Name of the name attribute for the tags
     * @var string
     */
    const ATTRIBUTE_NAME = 'name';

    /**
     * Name of the group attribute for the model tag
     * @var string
     */
    const ATTRIBUTE_GROUP = 'group';

    /**
     * Name of the model class attribute for the model tag
     * @var string
     */
    const ATTRIBUTE_MODEL_CLASS = 'modelClass';

    /**
     * Name of the data class attribute for the model tag
     * @var string
     */
    const ATTRIBUTE_DATA_CLASS = 'dataClass';

    /**
     * Name of the log attribute for the model tag
     * @var string
     */
    const ATTRIBUTE_LOG = 'log';

    /**
     * Name of the will block delete attribute for a model tag
     * @var string
     */
    const ATTRIBUTE_WILL_BLOCK_DELETE = 'willBlockDeleteWhenUsed';

    /**
     * Name of the field type attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_TYPE = 'type';

    /**
     * Name of the default value attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_DEFAULT = 'default';

    /**
     * Name of the label attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_LABEL = 'label';

    /**
     * Name of the localized attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_LOCALIZED = 'localized';

    /**
     * Name of the unique attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_UNIQUE = 'unique';

    /**
     * Name of the model attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_MODEL = 'model';

    /**
     * Name of the relation attribute for a field tag
     * @var unknown_type
     */
    const ATTRIBUTE_RELATION = 'relation';

    /**
     * Name of the relation order attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_RELATION_ORDER = 'relationOrder';

    /**
     * Name of the indexOn attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_INDEX_ON = 'indexOn';

    /**
     * Name of the link model attribute for a field tag
     * @var string
     */
    const ATTRIBUTE_LINK_MODEL = 'linkModel';

    /**
     * Name of the dependant attribute for a relation field tag
     * @var string
     */
    const ATTRIBUTE_DEPENDANT = 'dependant';

    /**
     * Name of the foreign key attribute for a relation field tag
     * @var string
     */
    const ATTRIBUTE_FOREIGN_KEY = 'foreignKey';

    /**
     * Name of the value attribute
     * @var string
     */
    const ATTRIBUTE_VALUE = 'value';

    /**
     * Instance of the reflection helper
     * @var pallo\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Constructs a new XML model IO
     * @param pallo\library\reflection\ReflectionHelper $reflectionHelper
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper) {
        $this->reflectionHelper = $reflectionHelper;
    }

    /**
     * Read models from a xml model definition file
     * @param pallo\library\filesystem\File $file
     * @return array Array with Model instances
     */
    protected function readModelsFromFile(File $file) {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;

        @$dom->load($file);

        $rootElement = $dom->documentElement;
        return $this->getModelsFromElement($rootElement, $file);
    }

    /**
     * Write the model definitions of the provided models to the provided model definition file
     * @param pallo\library\filesystem\File $file
     * @param array $models models to write to file
     * @return null
     */
    protected function writeModelsToFile(File $file, array $models) {
        if (!$models) {
            if ($file->exists()) {
                $file->delete();
            }

            return;
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        $modelsElement = $dom->createElement(self::TAG_ROOT);
        $dom->appendChild($modelsElement);

        foreach ($models as $model) {
            $modelElement = $this->getElementFromModel($dom, $model);
            if ($modelElement != null) {
                $importedModelElement = $dom->importNode($modelElement, true);
                $modelsElement->appendChild($importedModelElement);
            }
        }

        $dom->save($file);
    }

    /**
     * Get the models from the root element
     * @param DOMElement $rootElement root element of the xml document
     * @param pallo\library\filesystem\File $file the file which is being read
     * @return array Array with model instances
     * @throws pallo\library\orm\exception\OrmException when the root tag has a wrong name or when no models are defined in the document
     */
    protected function getModelsFromElement(DOMElement $rootElement, File $file) {
        if ($rootElement->tagName != self::TAG_ROOT) {
            throw new OrmException('No ' . self::TAG_ROOT . ' root tag found in ' . $file->getPath());
        }

        $modelElements = $rootElement->getElementsByTagName(self::TAG_MODEL);
        if ($modelElements->length == 0) {
            throw new OrmException('No ' . self::TAG_MODEL . ' tag found in ' . $file->getPath());
        }

        $models = array();
        foreach ($modelElements as $modelElement) {
            $model = $this->getModelFromElement($modelElement, $file);
            $models[$model->getName()] = $model;
        }

        return $models;
    }

    /**
     * Get the model from the model element
     * @param DOMElement $modelElement model element in the xml root element
     * @param pallo\library\filesystem\File $file the file which is being read
     * @return pallo\library\orm\Model Model instance created from the read model definition
     * @throws pallo\library\orm\exception\OrmException when the model element has no name attribute
     */
    protected function getModelFromElement(DOMElement $modelElement, File $file) {
        $modelName = $modelElement->getAttribute(self::ATTRIBUTE_NAME);
        if ($modelName == null) {
            throw new OrmException('No ' . self::ATTRIBUTE_NAME . ' attribute found for ' . self::TAG_MODEL . ' tag in ' . $file->getPath());
        }

        $modelClassName = $modelElement->hasAttribute(self::ATTRIBUTE_MODEL_CLASS) ?
                          $modelElement->getAttribute(self::ATTRIBUTE_MODEL_CLASS) :
                          OrmManager::DEFAULT_MODEL;

        $dataClassName = $modelElement->hasAttribute(self::ATTRIBUTE_DATA_CLASS) ?
                         $modelElement->getAttribute(self::ATTRIBUTE_DATA_CLASS) :
                         ModelMeta::CLASS_DATA;

        $willBlockDeleteWhenUsed = $modelElement->getAttribute(self::ATTRIBUTE_WILL_BLOCK_DELETE) ?
                    Boolean::getBoolean($modelElement->getAttribute(self::ATTRIBUTE_WILL_BLOCK_DELETE)) :
                    false;

        $modelTable = new ModelTable($modelName);
        $modelTable->setWillBlockDeleteWhenUsed($willBlockDeleteWhenUsed);
//         $modelTable->setValidationConstraint($validationConstraint);

        $fields = $this->getFieldsFromElement($modelElement, $file, $modelName);
        foreach ($fields as $field) {
            $modelTable->addField($field);
        }

        $this->setIndexesFromElement($modelElement, $modelTable);
        $this->setFormatsFromElement($modelElement, $modelTable);
        $this->setOptionsFromElement($modelElement, $modelTable);

        $behaviours = array();
        if ($modelTable->getOption('behaviour.date')) {
            $behaviours[] = new DatedBehaviour();
        }
        if ($modelTable->getOption('behaviour.version')) {
            $behaviours[] = new VersionBehaviour();
        }
        $slugField = $modelTable->getOption('behaviour.slug.field');
        if ($slugField) {
            $behaviours[] = new FieldSlugBehaviour(explode(',', $slugField));
        }
        if ($modelTable->getOption('behaviour.log')) {
            $behaviours[] = new LogBehaviour();
        }

        foreach ($fields as $field) {
            if ($field->isUnique()) {
                $behaviours[] = new UniqueBehaviour($field->getName());
            }
        }

        $arguments = array(
        	'reflectionHelper' => $this->reflectionHelper,
            'meta' => new ModelMeta($modelTable, $dataClassName),
            'behaviours' => $behaviours,
        );

        return $this->reflectionHelper->createObject($modelClassName, $arguments, OrmManager::INTERFACE_MODEL);
    }

    /**
     * Get the model fields from the model element
     * @param DOMElement $modelElement model element in the xml root element
     * @param pallo\library\filesystem\File $file the file which is being read
     * @return array Array with ModelField objects
     * @throws pallo\library\orm\exception\OrmException when the model element has no field elements
     */
    protected function getFieldsFromElement(DOMElement $modelElement, File $file, $modelName) {
        $fields = array();

        $fieldElements = $modelElement->getElementsByTagName(self::TAG_FIELD);
        if ($fieldElements->length == 0) {
            throw new OrmException('No ' . self::TAG_FIELD . ' tag found for ' . $modelName . ' in ' . $file->getPath());
        }

        foreach ($fieldElements as $fieldElement) {
            $fields[] = $this->getFieldFromElement($fieldElement, $file, $modelName);
        }

        return $fields;
    }

    /**
     * Get the ModelField from a field element
     * @param DOMElement $fieldElement field element in the model element
     * @param pallo\library\filesystem\File $file the file which is being read
     * @param string $modelName the model which is currently being processed
     * @return pallo\library\orm\definition\field\ModelField
     * @throws pallo\library\orm\exception\OrmException when the field element has no name attribute or when the field is defined as property and as relation field
     */
    protected function getFieldFromElement(DOMElement $fieldElement, File $file, $modelName) {
        $attributeName = self::ATTRIBUTE_NAME;
        $fieldName = $fieldElement->getAttribute($attributeName);

        if ($fieldName == null) {
            throw new OrmException("No {$attributeName} attribute found for field of {$modelName} in {$file->getPath()}");
        }

        $attributeType = self::ATTRIBUTE_TYPE;
        $fieldType = $fieldElement->getAttribute($attributeType);

        $attributeModel = self::ATTRIBUTE_MODEL;
        $fieldModel = $fieldElement->getAttribute($attributeModel);

        if ($fieldType == null && $fieldModel == null) {
            $fieldType == self::DEFAULT_FIELD_TYPE;
        } elseif ($fieldType != null && $fieldModel != null) {
            throw new OrmException("{$fieldName} of {$modelName} cannot have both the {$attributeType} and the {$attributeModel} attribute in {$file->getPath()}");
        }

        if ($fieldType != null) {
            $field = $this->getPropertyFieldFromElement($fieldElement, $file, $modelName, $fieldName, $fieldType);
        } else {
            $field = $this->getRelationFieldFromElement($fieldElement, $file, $modelName, $fieldName, $fieldModel);
        }

        $localized = $fieldElement->hasAttribute(self::ATTRIBUTE_LOCALIZED) ?
                     Boolean::getBoolean($fieldElement->getAttribute(self::ATTRIBUTE_LOCALIZED)) :
                     false;
        $field->setIsLocalized($localized);

        $validators = $this->getValidatorsFromFieldElement($fieldElement, $file, $modelName, $fieldName);
        foreach ($validators as $validatorName => $validatorOptions) {
            $field->addValidator($validatorName, $validatorOptions);
        }

        $this->setOptionsFromElement($fieldElement, $field);

        return $field;
    }

    /**
     * Get the ModelField from a property field element
     * @param DOMElement $fieldElement field element in the model element
     * @param pallo\library\filesystem\File $file the file which is being read
     * @param string $modelName the model which is currently being processed
     * @param string $fieldName the field which is currently being processed
     * @param string $fieldType the type of the field which is currently being processed
     * @return pallo\library\orm\definition\field\ModelField
     */
    protected function getPropertyFieldFromElement(DOMElement $fieldElement, File $file, $modelName, $fieldName, $fieldType) {
        $field = new PropertyField($fieldName, $fieldType);

        $default = $fieldElement->hasAttribute(self::ATTRIBUTE_DEFAULT) ?
                   $fieldElement->getAttribute(self::ATTRIBUTE_DEFAULT) :
                   null;
        $field->setDefaultValue($default);

        $unique = $fieldElement->hasAttribute(self::ATTRIBUTE_UNIQUE) ?
                  Boolean::getBoolean($fieldElement->getAttribute(self::ATTRIBUTE_UNIQUE)) :
                  false;
        $field->setIsUnique($unique);

        return $field;
    }

    /**
     * Get the ModelField from a relation field element
     * @param DOMElement $fieldElement field element in the model element
     * @param pallo\library\filesystem\File $file the file which is being read
     * @param string $modelName the model which is currently being processed
     * @param string $fieldName the field which is currently being processed
     * @param string $relationModelName the name of the model for which this field is a relation
     * @return pallo\library\orm\definition\field\ModelField
     * @throws pallo\library\orm\exception\OrmException when an invalid relation type has been defined
     */
    protected function getRelationFieldFromElement(DOMElement $fieldElement, File $file, $modelName, $fieldName, $relationModelName) {
        $relationType = $fieldElement->hasAttribute(self::ATTRIBUTE_RELATION) ?
                        $fieldElement->getAttribute(self::ATTRIBUTE_RELATION) :
                        self::DEFAULT_FIELD_RELATION;

        switch ($relationType) {
            case self::RELATION_BELONGS_TO:
                $field = new BelongsToField($fieldName, $relationModelName);
                break;
            case self::RELATION_HAS_ONE:
                $field = new HasOneField($fieldName, $relationModelName);
                break;
            case self::RELATION_HAS_MANY:
                $field = new HasManyField($fieldName, $relationModelName);

                $relationOrder = $fieldElement->hasAttribute(self::ATTRIBUTE_RELATION_ORDER) ?
                                 $fieldElement->getAttribute(self::ATTRIBUTE_RELATION_ORDER) :
                                 null;
                $field->setRelationOrder($relationOrder);

                $indexOn = $fieldElement->hasAttribute(self::ATTRIBUTE_INDEX_ON) ?
                           $fieldElement->getAttribute(self::ATTRIBUTE_INDEX_ON) :
                           null;
                $field->setIndexOn($indexOn);

                $linkModelName = $fieldElement->hasAttribute(self::ATTRIBUTE_LINK_MODEL) ?
                                 $fieldElement->getAttribute(self::ATTRIBUTE_LINK_MODEL) :
                                 null;
                $field->setLinkModelName($linkModelName);
                break;
            default:
                throw new OrmException("{$fieldName} of {$modelName} has an invalid relation ({$relationType}) in {$file->getPath()}");
                break;
        }

        $dependant = $fieldElement->hasAttribute(self::ATTRIBUTE_DEPENDANT) ?
                     Boolean::getBoolean($fieldElement->getAttribute(self::ATTRIBUTE_DEPENDANT)) :
                     false;
        $field->setIsDependant($dependant);

        $foreignKey = $fieldElement->hasAttribute(self::ATTRIBUTE_FOREIGN_KEY) ?
                     $fieldElement->getAttribute(self::ATTRIBUTE_FOREIGN_KEY) :
                     null;
        if ($foreignKey) {
            $field->setForeignKeyName($foreignKey);
        }

        return $field;
    }

    /**
     * Get the validators for a field
     * @param DOMElement $fieldElement field element in the model element
     * @param pallo\library\filesystem\File $file the file which is being read
     * @param string $modelName the model which is currently being processed
     * @param string $fieldName the field which is currently being processed
     * @return array Array with validator definitions
     * @throws pallo\library\orm\exception\OrmException when no name attribute is found in a validation tag
     */
    protected function getValidatorsFromFieldElement(DOMElement $fieldElement, File $file, $modelName, $fieldName) {
        $tagValidation = self::TAG_VALIDATION;
        $validatorElements = $fieldElement->getElementsByTagName($tagValidation);

        $validators = array();
        $attributeName = self::ATTRIBUTE_NAME;
        foreach ($validatorElements as $validatorElement) {
            $name = $validatorElement->getAttribute($attributeName);
            if ($name == null) {
                throw new OrmException("No {$attributeName} attribute found for {$tagValidation} tag in {$fieldName} of {$modelName} in {$file->getPath()}");
            }

            $options = $this->getValidationParametersFromValidationElement($validatorElement, $file, $modelName, $fieldName);

            $validators[$name] = $options;
        }

        return $validators;
    }

    /**
     * Creates an instance of a validator
     * @param string $name Name of the validator
     * @param array $options Options for the validator
     * @return pallo\library\validation\validator\Validator
     */
    abstract protected function createValidator($name, $options);

    /**
     * Get the parameters from a validator tag
     * @param DOMElement $element validator element in the field element
     * @param pallo\library\filesystem\File $file the file which is being read
     * @param string $modelName the model which is currently being processed
     * @param string $fieldName the field which is currently being processed
     * @return array Array with validator parameters
     * @throws pallo\library\orm\exception\OrmException when no name or value attribute is found in a parameter tag
     */
    protected function getValidationParametersFromValidationElement(DOMElement $element, File $file, $modelName, $fieldName) {
        $parameterElements = $element->getElementsByTagName(self::TAG_PARAMETER);

        $parameters = array();
        foreach ($parameterElements as $parameterElement) {
            $name = $parameterElement->getAttribute(self::ATTRIBUTE_NAME);
            if ($name == null) {
                throw new OrmException('No ' . self::ATTRIBUTE_NAME . ' attribute found for ' . self::TAG_PARAMETER . ' tag for validation in ' . $fieldName . ' of ' . $modelName . ' in ' . $file->getPath());
            }

            $value = $parameterElement->getAttribute('value');
            if ($value == null) {
                throw new OrmException('No ' . self::ATTRIBUTE_VALUE . ' attribute found for ' . self::TAG_PARAMETER . ' tag for validation ' . $name . ' in ' . $fieldName . ' of ' . $modelName . ' in ' . $file->getPath());
            }

            $parameters[$name] = $value;
        }

        return $parameters;
    }

    /**
     * Sets the the indexes to the model table
     * @param DOMElement $modelElement Element of the model
     * @param pallo\library\orm\definition\ModelTable $modelTable Model table which is being read
     * @return null
     */
    protected function setIndexesFromElement(DOMElement $modelElement, ModelTable $modelTable) {
        $indexElements = $modelElement->getElementsByTagName(self::TAG_INDEX);

        foreach ($indexElements as $indexElement) {
            $indexFields = array();

            $indexFieldElements = $indexElement->getElementsByTagName(self::TAG_INDEX_FIELD);
            foreach ($indexFieldElements as $indexFieldElement) {
                $fieldName = $indexFieldElement->getAttribute(self::ATTRIBUTE_NAME);
                $indexFields[$fieldName] = $modelTable->getField($fieldName);
            }

            $indexName = $indexElement->getAttribute(self::ATTRIBUTE_NAME);

            $index = new Index($indexName, $indexFields);

            $modelTable->setIndex($index);
        }
    }

    /**
     * Sets the the title and teaser format to the model table
     * @param DOMElement $modelElement Element of the model
     * @param pallo\library\orm\definition\ModelTable $modelTable Model table which is being read
     * @return null
     */
    protected function setFormatsFromElement(DOMElement $modelElement, ModelTable $modelTable) {
        $formatElements = $modelElement->getElementsByTagName(self::TAG_FORMAT);

        foreach ($formatElements as $formatElement) {
            $name = $formatElement->getAttribute(self::ATTRIBUTE_NAME);
            $format = $formatElement->textContent;

            $modelTable->setDataFormat($name, $format);
        }
    }

    /**
     * Sets the the extra properties to the model table
     * @param DOMElement $modelElement Element of the model
     * @param pallo\library\orm\definition\ModelTable $modelTable Model table which is being read
     * @return null
     */
    protected function setOptionsFromElement(DOMElement $element, $data) {
        $optionElements = $element->getElementsByTagName(self::TAG_OPTION);
        if (!$optionElements) {
            return;
        }

        $options = array();

        foreach ($optionElements as $optionElement) {
            $name = $optionElement->getAttribute(self::ATTRIBUTE_NAME);
            $value = $optionElement->getAttribute(self::ATTRIBUTE_VALUE);

            $options[$name] = $value;
        }

        $data->setOptions($options);
    }

    /**
     * Create a xml element with the definition of a model
     * @param pallo\library\xml\dom\Document $dom
     * @param pallo\library\orm\Model $model
     * @return DOMElement an xml element which defines the model
     */
    protected function getElementFromModel(Document $dom, Model $model) {
        $meta = $model->getMeta();
        $modelTable = $meta->getModelTable();

        $modelClass = get_class($model);
        $dataClass = $meta->getDataClassName();
        $group = $modelTable->getGroup();

        $modelElement = $dom->createElement(self::TAG_MODEL);
        $modelElement->setAttribute(self::ATTRIBUTE_NAME, $model->getName());
        if ($meta->isLogged()) {
            $modelElement->setAttribute(self::ATTRIBUTE_LOG, 'true');
        }
        $modelElement->setAttribute(self::ATTRIBUTE_MODEL_CLASS, $modelClass);
        $modelElement->setAttribute(self::ATTRIBUTE_DATA_CLASS, $dataClass);
        if ($meta->willBlockDeleteWhenUsed()) {
            $modelElement->setAttribute(self::ATTRIBUTE_WILL_BLOCK_DELETE, 'true');
        }
        if ($group) {
            $modelElement->setAttribute(self::ATTRIBUTE_GROUP, $group);
        }

        $fields = $modelTable->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY) {
                continue;
            }

            $fieldElement = $this->getElementFromField($dom, $field);
            $importedFieldElement = $dom->importNode($fieldElement, true);
            $modelElement->appendChild($importedFieldElement);
        }

        $indexes = $modelTable->getIndexes();
        foreach ($indexes as $index) {
            $indexElement = $this->getElementFromIndex($dom, $index);
            $modelElement->appendChild($indexElement);
        }

        $dataFormats = $modelTable->getDataFormats(false);
        foreach ($dataFormats as $dataFormat) {
            $formatElement = $dom->createElement(self::TAG_FORMAT, $dataFormat->getFormat());
            $formatElement->setAttribute(self::ATTRIBUTE_NAME, $dataFormat->getName());
            $modelElement->appendChild($formatElement);
        }

        return $modelElement;
    }

    /**
     * Create a xml element with the definition of a model field
     * @param pallo\library\xml\dom\Document $dom
     * @param pallo\library\orm\definition\field\ModelField $field
     * @return DOMElement an xml element which defines the model field
     */
    protected function getElementFromField(Document $dom, ModelField $field) {
        $element = $dom->createElement(self::TAG_FIELD);
        $element->setAttribute(self::ATTRIBUTE_NAME, $field->getName());

        if ($field instanceof RelationField) {
            $element->setAttribute(self::ATTRIBUTE_MODEL, $field->getRelationModelName());
            if ($field instanceof BelongsToField) {
                $element->setAttribute(self::ATTRIBUTE_RELATION, self::RELATION_BELONGS_TO);
            } elseif ($field instanceof HasOneField) {
                $element->setAttribute(self::ATTRIBUTE_RELATION, self::RELATION_HAS_ONE);
            } elseif ($field instanceof HasManyField) {
                $element->setAttribute(self::ATTRIBUTE_RELATION, self::RELATION_HAS_MANY);
                $linkModel = $field->getLinkModelName();
                if ($linkModel != null) {
                    $element->setAttribute(self::ATTRIBUTE_LINK_MODEL, $linkModel);
                }

                $indexOn = $field->getIndexOn();
                if ($indexOn) {
                    $element->setAttribute(self::ATTRIBUTE_INDEX_ON, $indexOn);
                }
            }

            if ($field->isDependant()) {
                $element->setAttribute(self::ATTRIBUTE_DEPENDANT, 'true');
            }

            $foreignKey = $field->getForeignKeyName();
            if ($foreignKey) {
                $element->setAttribute(self::ATTRIBUTE_FOREIGN_KEY, $foreignKey);
            }
        } else {
            $element->setAttribute(self::ATTRIBUTE_TYPE, $field->getType());
            $default = $field->getDefaultValue();
            if ($default != null) {
                $element->setAttribute(self::ATTRIBUTE_DEFAULT, $default);
            }
            if ($field->IsUnique()) {
                $element->setAttribute(self::ATTRIBUTE_UNIQUE, 'true');
            }
        }

        if ($field->isLocalized()) {
            $element->setAttribute(self::ATTRIBUTE_LOCALIZED, 'true');
        }

        $validators = $field->getValidators();
        foreach ($validators as $validator) {
            $validatorElement = $dom->createElement(self::TAG_VALIDATION);
            $validatorElement->setAttribute(self::ATTRIBUTE_NAME, $validator->getName());

            $options = $validator->getOptions();
            foreach ($options as $key => $value) {
                $parameterElement = $dom->createElement(self::TAG_PARAMETER);
                $parameterElement->setAttribute(self::ATTRIBUTE_NAME, $key);
                $parameterElement->setAttribute(self::ATTRIBUTE_VALUE, $value);

                $validatorElement->appendChild($parameterElement);
            }

            $element->appendChild($validatorElement);
        }

        $options = $field->getOptions();
        foreach ($options as $name => $value) {
            $optionElement = $dom->createElement(self::TAG_OPTION);
            $optionElement->setAttribute(self::ATTRIBUTE_NAME, $name);
            $optionElement->setAttribute(self::ATTRIBUTE_VALUE, $value);

            $element->appendChild($optionElement);
        }

        return $element;
    }

    /**
     * Gets the index element for the provided index
     * @param pallo\library\xml\dom\Document $dom
     * @param pallo\library\database\definition\Index $index Index to get the element from
     * @return DOMElement
     */
    protected function getElementFromIndex(Document $dom, Index $index) {
        $indexName = $index->getName();

        $indexElement = $dom->createElement(self::TAG_INDEX);
        $indexElement->setAttribute(self::ATTRIBUTE_NAME, $indexName);

        $fields = $index->getFields();
        foreach ($fields as $field) {
            $fieldElement = $dom->createElement(self::TAG_INDEX_FIELD);
            $fieldElement->setAttribute(self::ATTRIBUTE_NAME, $field->getName());
            $indexElement->appendChild($fieldElement);
        }

        return $indexElement;
    }

}