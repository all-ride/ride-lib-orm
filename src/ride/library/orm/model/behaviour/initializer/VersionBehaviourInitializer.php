<?php

namespace ride\library\orm\model\behaviour\initializer;

use ride\library\generator\CodeClass;
use ride\library\generator\CodeGenerator;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\behaviour\VersionBehaviour;

/**
 * Setup the version behaviour based on the model options
 */
class VersionBehaviourInitializer implements BehaviourInitializer {

    /**
     * Gets the behaviours for the model of the provided model table
     * @param \ride\library\orm\definition\ModelTable $modelTable
     * @return array An array with instances of Behaviour
     * @see \ride\library\orm\model\behaviour\Behaviour
     */
    public function getBehavioursForModel(ModelTable $modelTable) {
        if (!$modelTable->getOption('behaviour.version')) {
            return array();
        }

        if (!$modelTable->hasField('version')) {
            $versionField = new PropertyField('version', 'integer');
            $versionField->setOptions(array(
                'label.name' => 'label.version',
                'scaffold.form.omit' => 'true',
            ));

            $modelTable->addField($versionField);
        }

        return array(new VersionBehaviour());
    }

    /**
     * Generates the needed code for the entry class of the provided model table
     * @param \ride\library\orm\definition\ModelTable $table
     * @param \ride\library\generator\CodeGenerator $generator
     * @param \ride\library\generator\CodeClass $class
     * @return null
     */
    public function generateEntryClass(ModelTable $modelTable, CodeGenerator $generator, CodeClass $class) {
        if (!$modelTable->getOption('behaviour.version')) {
            return;
        }

        $class->addImplements('ride\\library\\orm\\entry\\VersionedEntry');
    }

}
