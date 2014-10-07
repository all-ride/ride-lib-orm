<?php

namespace ride\library\orm\loader;

use ride\library\database\definition\Index;
use ride\library\orm\definition\field\BelongsToField;
use ride\library\orm\definition\field\HasField;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\field\RelationField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\exception\ModelException;
use ride\library\orm\exception\OrmException;
use ride\library\orm\meta\ModelMeta;
use ride\library\orm\model\behaviour\LogBehaviour;
use ride\library\orm\model\GenericModel;
use ride\library\orm\model\LocalizedModel;
use ride\library\orm\model\Model;
use ride\library\reflection\ReflectionHelper;

/**
 * Register of the models. This register will handle the localized models and
 * the link models when (un)registering models.
 */
class ModelRegister {

    /**
     * Instance of the reflection helper
     * @var \ride\library\reflection\ReflectionHelper
     */
    private $reflectionHelper;

    /**
     * Array with the registered models
     * @var array
     */
    private $models;

    /**
     * Namespace for the default classes
     * @var string
     */
    protected $defaultNamespace;

    /**
     * Constructs a new model register
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, $defaultNamespace) {
        $this->reflectionHelper = $reflectionHelper;
        $this->models = array();
        $this->defaultNamespace = $defaultNamespace;
    }

    /**
     * Gets whether this register has a model by the provided name
     * @param string $modelName Name of the model
     * @return boolean True if the register has the provided model registered,
     * false otherwise
     * @throws \ride\library\orm\exception\OrmException when the provided model
     * name is empty
     */
    public function hasModel($modelName) {
        if (!is_string($modelName) || $modelName == '') {
            throw new OrmException('Provided modelname is empty or invalid');
        }

        return isset($this->models[$modelName]);
    }

    /**
     * Gets a model from the register
     * @param string $modelName Name of the model
     * @return \ride\library\orm\model\Model The requested model
     * @throws \ride\library\orm\exception\OrmException when the requested model
     * is not registered
     */
    public function getModel($modelName) {
        if (!$this->hasModel($modelName)) {
            throw new OrmException('Model ' . $modelName . ' is not registered');
        }

        return $this->models[$modelName];
    }

    /**
     * Gets all the registered models
     * @return array Array with the model name as key and the Model object as
     * value
     */
    public function getModels() {
        return $this->models;
    }

    /**
     * Registers an array of models
     * @param array $models Array of Model objects
     * @return null
     */
    public function registerModels(array $models) {
        foreach ($models as $model) {
            $this->registerModel($model, false);
        }

        $this->updateLinkModels();
        $this->updateUnlinkedModels();
    }

    /**
     * Registers a model
     * @param \ride\library\orm\model\Model $model Model to register
     * @param boolean $updateUnlinkedModels Flag to set whether to update the
     * meta of the unlinked models
     * @return null
     */
    public function registerModel(Model $model, $updateUnlinkedModels = true) {
        $this->models[$model->getName()] = $model;

        $this->registerLocalizedModel($model);

        $this->registerLinkModels($model);

        if ($updateUnlinkedModels) {
            $this->updateLinkModels();
            $this->updateUnlinkedModels();
        }
    }

    /**
     * Unregisters a model
     * @param string $modelName Name of the model to unregister
     * @return null
     * @throws \ride\library\orm\exception\OrmException when the requested model
     * is not registered
     */
    public function unregisterModel($modelName) {
        $model = $this->getModel($modelName);

        unset($this->models[$modelName]);

        $this->unregisterLinkModels($model);

        if ($model->getMeta()->isLocalized()) {
            $this->unregisterModel($modelName . LocalizedModel::MODEL_SUFFIX);
        }

        $this->updateUnlinkedModels();
    }

    /**
     * Updates the unlinked models of the model meta's
     * @return null
     */
    private function updateUnlinkedModels() {
        foreach ($this->models as $model) {
            $unlinkedModels = array();

            $modelName = $model->getName();
            $modelMeta = $model->getMeta();

            $relationFields = $modelMeta->getFields();
            foreach ($relationFields as $fieldName => $field) {
                if ($field instanceof RelationField) {
                    continue;
                }

                unset($relationFields[$fieldName]);
            }

            foreach ($this->models as $unlinkedModel) {
                if ($unlinkedModel->getName() == $modelName || $unlinkedModel->getName() == $modelName . ModelMeta::SUFFIX_LOCALIZED) {
                    continue;
                }

                $unlinkedModelMeta = $unlinkedModel->getMeta();
                if (!$unlinkedModelMeta->hasRelationWith($modelName, ModelTable::BELONGS_TO)) {
                    continue;
                }

                $unlinkedModelName = $unlinkedModel->getName();

                $isUnlinkedModel = true;
                foreach ($relationFields as $field) {
                    if ($field->getRelationModelName() == $unlinkedModelName || $field->getLinkModelName() == $unlinkedModelName) {
                        $isUnlinkedModel = false;
                        break;
                    }
                }

                if ($isUnlinkedModel) {
                    $unlinkedModels[] = $unlinkedModelName;
                }
            }

            $modelMeta->setUnlinkedModels($unlinkedModels);
        }
    }

    /**
     * Registers the localized model of the provided model if needed
     * @param \ride\library\orm\model\Model $model
     * @return null
     */
    private function registerLocalizedModel(Model $model) {
        if (!$model->getMeta()->isLocalized()) {
            return;
        }

        $localizedModelName = $model->getName() . ModelMeta::SUFFIX_LOCALIZED;

        $modelTable = $model->getMeta()->getModelTable();
        $group = $modelTable->getOption('group');

        $dataField = new BelongsToField(LocalizedModel::FIELD_ENTRY, $model->getName());
        $localeField = new PropertyField(LocalizedModel::FIELD_LOCALE, 'string');

        $localeIndex = new Index(LocalizedModel::FIELD_LOCALE, array($localeField));
        $dataLocaleIndex = new Index(LocalizedModel::FIELD_LOCALE . ucfirst(LocalizedModel::FIELD_ENTRY), array($localeField, $dataField));

        $isLogged = false;

        $behaviours = $model->getBehaviours();
        foreach ($behaviours as $behaviour) {
            if ($behaviour instanceof LogBehaviour) {
                $isLogged = true;
            }
        }

        $behaviours = array();
        if ($isLogged) {
            $behaviours[] = new LogBehaviour();
        }

        $localizedModelTable = new ModelTable($localizedModelName);
        $localizedModelTable->addField($dataField);
        $localizedModelTable->addField($localeField);
        $localizedModelTable->addIndex($localeIndex);
        $localizedModelTable->addIndex($dataLocaleIndex);

        if ($group) {
            $localizedModelTable->setOption('group', $group);
        }

        $fields = $model->getMeta()->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY || !$field->isLocalized()) {
                continue;
            }

            $field = clone($field);
            $field->setIsLocalized(false);

            $localizedModelTable->addField($field);
        }

        $entryClassName = $this->defaultNamespace . '\\' . $localizedModelName . 'Entry';
        $proxyClassName = $this->defaultNamespace . '\\proxy\\' . $localizedModelName . 'EntryProxy';

        $localizedModel = new LocalizedModel($model->getReflectionHelper(), new ModelMeta($localizedModelTable, $entryClassName, $proxyClassName), $behaviours);

        $this->registerModel($localizedModel, false);
    }

    /**
     * Registers the link models for all registered models
     * @return null
     */
    private function updateLinkModels() {
        foreach ($this->models as $model) {
            $this->registerLinkModels($model);
        }
    }

    /**
     * Registers the link models needed for the provided model
     * @param \ride\library\orm\model\Model $model
     * @return null
     */
    private function registerLinkModels(Model $model) {
        $modelName = $model->getName();
        $fields = $model->getMeta()->getFields();

        foreach ($fields as $fieldName => $field) {
            if (!($field instanceof HasField) || $field->isLocalized()) {
                continue;
            }

            $relationModelName = $field->getRelationModelName();
            if (!$this->hasModel($relationModelName)) {
                continue;
            }

            $relationModel = $this->getModel($relationModelName);

            $relations = $relationModel->getMeta()->getRelation($modelName);

            $numBelongsTo = count($relations[ModelTable::BELONGS_TO]);
            $numHasOne = count($relations[ModelTable::HAS_ONE]);
            $numHasMany = count($relations[ModelTable::HAS_MANY]);
            $numRelations = $numBelongsTo + $numHasOne + $numHasMany;

            if (!$numRelations) {
                $belongsTo = null;

                if (preg_match('/' . ModelMeta::SUFFIX_LOCALIZED . '$/', $modelName)) {
                    $unlocalizedModelName = substr($modelName, 0, strlen(ModelMeta::SUFFIX_LOCALIZED) * -1);
                    $unlocalizedRelations = $relationModel->getMeta()->getRelation($unlocalizedModelName);

                    if ($unlocalizedRelations[ModelTable::BELONGS_TO]) {
                        $belongsTo = array_shift($unlocalizedRelations[ModelTable::BELONGS_TO]);
                        if ($belongsTo->isLocalized()) {
                            continue;
                        }
                    }
                }

                $linkModel = $this->registerLinkModel($model, $fieldName);

                $this->updateLinkModelGroup($model, $relationModel, $linkModel);

                if ($belongsTo) {
                    $belongsTo->setLinkModelName($field->getLinkModelName());
                }

                continue;
            }

            if ($numRelations == 1) {
                if ($numBelongsTo) {
                    $belongsToField = array_pop($relations[ModelTable::BELONGS_TO]);
                    $field->setForeignKeyName($belongsToField->getName());
                    $field->setLinkModelName(null);

                    continue;
                }

                if ($numHasOne) {
                    $hasField = array_pop($relations[ModelTable::HAS_ONE]);
                } else {
                    $hasField = array_pop($relations[ModelTable::HAS_MANY]);
                }

                $linkModel = $this->registerManyToManyLinkModel($model, $fieldName, $hasField);

                $this->updateLinkModelGroup($model, $relationModel, $linkModel);

                continue;
            }

            $foreignKey = $field->getForeignKeyName();
            if ($foreignKey) {
                if (isset($relations[ModelTable::BELONGS_TO][$foreignKey])) {
                    $field->setLinkModelName(null);

                    continue;
                }

                if (isset($relations[ModelTable::HAS_MANY][$foreignKey])) {
                    $hasField = $relations[ModelTable::HAS_MANY][$foreignKey];
                } elseif (isset($relations[ModelTable::HAS_ONE][$foreignKey])) {
                    $hasField = $relations[ModelTable::HAS_ONE][$foreignKey];
                } else {
                    throw new OrmException('Foreign key ' . $foreignKey . ' was not found in ' . $relationModelName);
                }

                $linkModel = $this->registerManyToManyLinkModel($model, $fieldName, $hasField);

                $this->updateLinkModelGroup($model, $relationModel, $linkModel);

                continue;
            }

            $linkModel = $field->getLinkModelName();
            $hasField = null;

            $relations = $relations[ModelTable::HAS_MANY] + $relations[ModelTable::HAS_ONE];
            foreach ($relations as $relationField) {
                if ($relationField->getLinkModelName() == $linkModel) {
                    $hasField = $relationField;
                    break;
                }
            }

            if ($hasField) {
                $linkModel = $this->registerManyToManyLinkModel($model, $fieldName, $hasField);
            } else {
                $linkModel = $this->registerLinkModel($model, $fieldName);
            }

            $this->updateLinkModelGroup($model, $relationModel, $linkModel);
        }
    }

    /**
     * Unregisters the link models used by the provided model
     * @param \ride\library\orm\model\Model $model
     * @return null
     */
    private function unregisterLinkModels(Model $model) {
        $fields = $model->getMeta()->getFields();

        foreach ($fields as $fieldName => $field) {
            if (!($field instanceof RelationField)) {
                continue;
            }

            $linkModelName = $field->getLinkModelName();
            if ($linkModelName == null || !$this->hasModel($linkModelName)) {
                continue;
            }

            $relationModelName = $field->getRelationModelName();

            if ($this->hasModel($relationModelName)) {
                $relationModel = $this->getModel($relationModelName);

                if ($relationModel->getMeta()->hasRelationWith($model->getName())) {
                    continue;
                }
            }

            $this->unregisterModel($linkModelName);
        }
    }

    /**
     * Registeres a link model for the provided relation field
     * @param \ride\library\orm\model\Model $model
     * @param string $fieldName
     * @return \ride\library\orm\model\Model The registered model
     */
    private function registerLinkModel(Model $model, $fieldName) {
        $modelName = $model->getName();
        $modelMeta = $model->getMeta();

        $field = $modelMeta->getField($fieldName);

        $relationModelName = $field->getRelationModelName();
        $linkModelName = $field->getLinkModelName();

        if (!$linkModelName) {
            $linkModelName = $this->generateLinkModelName($modelName, $relationModelName);
            $linkModelName = $this->getUniqueLinkModelName($modelMeta, $fieldName, $linkModelName);

            $field->setLinkModelName($linkModelName);
        }

        if ($this->hasModel($linkModelName)) {
            return;
        }

        return $this->createAndRegisterLinkModel($linkModelName, $modelName, $relationModelName);
    }

    /**
     * Registeres a link model for a many to many relation
     * @param \ride\library\orm\definition\field\HasField $field1
     * @param \ride\library\orm\definition\field\HasField $field2
     * @return \ride\library\orm\model\Model The registered model
     */
    private function registerManyToManyLinkModel(Model $model, $fieldName, HasField $field2) {
        $modelName = $model->getName();
        $modelMeta = $model->getMeta();

        $field1 = $modelMeta->getField($fieldName);

        $linkModelName = $this->getManyToManyLinkModelName($field1, $field2, $modelMeta, $fieldName);

        $field1->setLinkModelName($linkModelName);
        $field2->setLinkModelName($linkModelName);

        $field1->setForeignKeyName($field2->getName());
        $field2->setForeignKeyName($field1->getName());

        if ($this->hasModel($linkModelName)) {
            return;
        }

        return $this->createAndRegisterLinkModel($linkModelName, $field1->getRelationModelName(), $field2->getRelationModelName());
    }

    /**
     * Creates a link model and registers it
     * @param string $linkModelName Name of the new link model
     * @param string $modelName1 Name of the first model
     * @param string $modelName2 Name of the second model
     * @return \ride\library\orm\model\Model The registered model
     */
    private function createAndRegisterLinkModel($linkModelName, $modelName1, $modelName2) {
        $table = new ModelTable($linkModelName);

        $indexName = lcfirst($this->generateLinkModelName($modelName1, $modelName2));

        $fieldName1 = lcfirst($modelName1);
        $fieldName2 = lcfirst($modelName2);

        if ($modelName1 == $modelName2) {
            $field1 = new BelongsToField($fieldName1 . '1', $modelName1);
            $field2 = new BelongsToField($fieldName1 . '2', $modelName2);
        } else {
            $field1 = new BelongsToField($fieldName1, $modelName1);
            $field2 = new BelongsToField($fieldName2, $modelName2);
        }

        $index = new Index($indexName, array($field1, $field2));

        $table->addField($field1);
        $table->addField($field2);
        $table->addIndex($index);

        $entryClassName = $this->defaultNamespace . '\\' . $linkModelName . 'Entry';
        $proxyClassName = $this->defaultNamespace . '\\proxy\\' . $linkModelName . 'EntryProxy';

        $linkModel = new GenericModel($this->reflectionHelper, new ModelMeta($table, $entryClassName, $proxyClassName));

        $this->registerModel($linkModel, false);

        return $linkModel;
    }

    /**
     * Gets the link model name for a many to many relation
     * @param \ride\library\orm\definition\field\HasManyField $field1
     * @param \ride\library\orm\definition\field\HasManyField $field2
     * @param \ride\library\orm\model\meta\ModelMeta $modelMeta Meta of the
     * model of the field
     * @param string $fieldName Field for the link model
     * @return string Name of the link model
     * @throws \ride\library\orm\exception\ModelException when field1 and
     * field2 have the link model set but they are not the same
     */
    private function getManyToManyLinkModelName(HasManyField $field1, HasManyField $field2, ModelMeta $modelMeta, $fieldName) {
        $linkModelName1 = $field1->getLinkModelName();
        $linkModelName2 = $field2->getLinkModelName();

        if ($linkModelName1 && $linkModelName2) {
            // if ($linkModelName1 != $linkModelName2) {
            //     throw new ModelException('Link model names of ' . $field1->getName() . ' and ' . $field2->getName() . ' are not equal');
            // }

            return $linkModelName1;
        }

        if ($linkModelName1) {
            return $linkModelName1;
        } elseif ($linkModelName2) {
            return $linkModelName2;
        }

        $modelName1 = $field1->getRelationModelName();
        $modelName2 = $field2->getRelationModelName();

        $linkModelName = $this->generateLinkModelName($modelName1, $modelName2);

        return $this->getUniqueLinkModelName($modelMeta, $fieldName, $linkModelName);
    }

    /**
     * Gets a unique link model name for the link model of the provided field
     * @param \ride\library\orm\model\meta\ModelMeta $modelMeta Meta of the
     * model of the field
     * @param string $fieldName Name of the field for the link model
     * @param string $linkModelName Name of the link model
     * @return string Unique name for the link model
     */
    private function getUniqueLinkModelName(ModelMeta $modelMeta, $fieldName, $linkModelName) {
        $index = 2;

        $fields = $modelMeta->getFields();

        $isLinkModelNameValid = false;
        $tmpLinkModelName = $linkModelName;
        do {
            $isLinkModelNameValid = true;

            foreach ($fields as $name => $field) {
                if ($fieldName == $name || !($field instanceof HasField)) {
                    continue;
                }

                if ($field->getLinkModelName() == $tmpLinkModelName) {
                    $isLinkModelNameValid = false;

                    break;
                }
            }

            if ($isLinkModelNameValid) {
                break;
            }

            $tmpLinkModelName = $linkModelName . $index;

            $index++;
        } while ($isLinkModelNameValid == false);

        return $tmpLinkModelName;
    }

    /**
     * Gets a link model name for the provided models
     * @param string $modelName1 Name of the first model
     * @param string $modelName2 Name of the second model
     * @return string Name for the link model between the provided models
     */
    private function generateLinkModelName($modelName1, $modelName2) {
        if ($modelName1 < $modelName2) {
            return $modelName1 . $modelName2;
        } else {
            return $modelName2 . $modelName1;
        }
    }

    /**
     * Updates the group of the link model according to the groups of the
     * references models
     * @param \ride\library\orm\model\Model $model Model of the link field
     * @param \ride\library\orm\model\Model $relationModel Model referenced by
     * the link field
     * @param \ride\library\orm\model\Model $linkModel Model used to link the
     * model and the relation model
     * @return null
     */
    private function updateLinkModelGroup(Model $model, Model $relationModel, Model $linkModel = null) {
        if (!$linkModel) {
            return;
        }

        $modelGroup = $model->getMeta()->getModelTable()->getOption('group');
        $relationGroup = $model->getMeta()->getModelTable()->getOption('group');

        if ($modelGroup != null && $modelGroup == $relationGroup) {
            $linkModel->getMeta()->getModelTable()->setOption('group', $modelGroup);
        }
    }

}
