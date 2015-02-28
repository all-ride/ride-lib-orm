<?php

namespace ride\library\orm\model\behaviour\initializer;

use ride\library\generator\CodeClass;
use ride\library\generator\CodeGenerator;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\behaviour\UniqueBehaviour;

/**
 * Setup the unique behaviours based on the model fields
 */
class UniqueBehaviourInitializer implements BehaviourInitializer {

    /**
     * Gets the behaviours for the model of the provided model table
     * @param \ride\library\orm\definition\ModelTable $modelTable
     * @return array An array with instances of Behaviour
     * @see \ride\library\orm\model\behaviour\Behaviour
     */
    public function getBehavioursForModel(ModelTable $modelTable) {
        $behaviours = array();

        $fields = $modelTable->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($fieldName == ModelTable::PRIMARY_KEY) {
                continue;
            }

            if ($field->isUnique()) {
                $behaviours[] = new UniqueBehaviour($fieldName);
            }
        }

        return $behaviours;
    }

    /**
     * Generates the needed code for the entry class of the provided model table
     * @param \ride\library\orm\definition\ModelTable $table
     * @param \ride\library\generator\CodeGenerator $generator
     * @param \ride\library\generator\CodeClass $class
     * @return null
     */
    public function generateEntryClass(ModelTable $modelTable, CodeGenerator $generator, CodeClass $class) {

    }

}
