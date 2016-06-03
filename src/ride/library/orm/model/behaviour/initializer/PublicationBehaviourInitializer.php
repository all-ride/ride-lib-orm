<?php

namespace ride\library\orm\model\behaviour\initializer;

use ride\library\generator\CodeClass;
use ride\library\generator\CodeGenerator;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\behaviour\PublicationBehaviour;
use ride\library\reflection\Boolean;

use InvalidArgumentException;

/**
 * Setup the Publication behaviour based on the model options
 */
class PublicationBehaviourInitializer implements BehaviourInitializer {

    /**
     * Gets the behaviours for the model of the provided model table
     * @param \ride\library\orm\definition\ModelTable $modelTable
     * @return array An array with instances of Behaviour
     * @see \ride\library\orm\model\behaviour\Behaviour
     */
    public function getBehavioursForModel(ModelTable $modelTable) {
        if (!$modelTable->getOption('behaviour.publication')) {
            return array();
        }
        
        if (!$modelTable->hasField('datePublished')) {
            $datePublishedField = new PropertyField('datePublished', 'datetime');
            $options = array(
                'label.name' => 'label.date.published',
                'scaffold.order' => 'true',
            );
            $tabValue = $this->getTabValue($modelTable);
            if ($tabValue) {
                $options['scaffold.form.tab'] = $tabValue;
            }
            $datePublishedField->addValidator('required', array());

            $datePublishedField->setOptions($options);
            
            $modelTable->addField($datePublishedField);
        }

        if (!$modelTable->hasField('isPublished')) {
            $isPublishedField = new PropertyField('isPublished', 'boolean', 0);
            $options = array(
                'label.name' => 'label.entry.isPublished',
                'label.description' => 'label.entry.isPublished.description',
            );
            $isPublishedField->setOptions($options);

            $tabValue = $this->getTabValue($modelTable);
            if ($tabValue) {
                $options['scaffold.form.tab'] = $tabValue;
            }
            $isPublishedField->setOptions($options);

            $modelTable->addField($isPublishedField);
        }

        return array(new PublicationBehaviour());
    }

    /**
     * Generates the needed code for the entry class of the provided model table
     * @param \ride\library\orm\definition\ModelTable $table
     * @param \ride\library\generator\CodeGenerator $generator
     * @param \ride\library\generator\CodeClass $class
     * @return null
     */
    public function generateEntryClass(ModelTable $modelTable, CodeGenerator $generator, CodeClass $class) {
        if (!$modelTable->getOption('behaviour.publication')) {
            return;
        }

        $class->addImplements('ride\\library\\orm\\entry\\PublishedEntry');

    }

    /**
     * Checks if the value of this behaviour is a boolean or not. When false it returns the value (which is the tab the fields will show)
     * @param \ride\library\orm\definition\ModelTable $modelTable
     * @return mixed
     */
    protected function getTabValue(ModelTable $modelTable) {
        $publicationValue = $modelTable->getOption('behaviour.publication');

        try {
            Boolean::getBoolean($publicationValue);

        } catch (InvalidArgumentException $exception) {
            return $publicationValue;
        }
    }

}
