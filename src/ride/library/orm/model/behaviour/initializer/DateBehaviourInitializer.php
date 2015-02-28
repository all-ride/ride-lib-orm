<?php

namespace ride\library\orm\model\behaviour\initializer;

use ride\library\generator\CodeClass;
use ride\library\generator\CodeGenerator;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\behaviour\DateBehaviour;

/**
 * Setup the date behaviour based on the model options
 */
class DateBehaviourInitializer implements BehaviourInitializer {

    /**
     * Gets the behaviours for the model of the provided model table
     * @param \ride\library\orm\definition\ModelTable $modelTable
     * @return array An array with instances of Behaviour
     * @see \ride\library\orm\model\behaviour\Behaviour
     */
    public function getBehavioursForModel(ModelTable $modelTable) {
        if (!$modelTable->getOption('behaviour.date')) {
            return array();
        }

        if (!$modelTable->hasField('dateAdded')) {
            $dateAddedField = new PropertyField('dateAdded', 'datetime');
            $dateAddedField->setOptions(array(
                'label.name' => 'label.date.added',
                'scaffold.form.omit' => 'true',
                'scaffold.order' => 'true',
            ));

            $modelTable->addField($dateAddedField);
        }

        if (!$modelTable->hasField('dateModified')) {
            $dateModifiedField = new PropertyField('dateModified', 'datetime');
            $dateModifiedField->setOptions(array(
                'label.name' => 'label.date.modified',
                'scaffold.form.omit' => 'true',
                'scaffold.order' => 'true',
            ));

            $modelTable->addField($dateModifiedField);
        }

        return array(new DateBehaviour());
    }

    /**
     * Generates the needed code for the entry class of the provided model table
     * @param \ride\library\orm\definition\ModelTable $table
     * @param \ride\library\generator\CodeGenerator $generator
     * @param \ride\library\generator\CodeClass $class
     * @return null
     */
    public function generateEntryClass(ModelTable $modelTable, CodeGenerator $generator, CodeClass $class) {
        if (!$modelTable->getOption('behaviour.date')) {
            return;
        }

        $class->addImplements('ride\\library\\orm\\entry\\DatedEntry');

        $timestampArgument = $generator->createVariable('timestamp', 'integer');
        $timestampArgument->setDescription('UNIX timestamp of the date');

        $setterCode =
'if ($this->getDateAdded()) {
    return;
}

$this->dateAdded = $timestamp;
$this->dateModified = $timestamp;';

        $setterMethod = $generator->createMethod('setDateAdded', array($timestampArgument), $setterCode);
        $setterMethod->setDescription('Sets the add date');

        $class->addMethod($setterMethod);
    }

}
