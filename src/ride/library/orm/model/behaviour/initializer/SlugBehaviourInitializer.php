<?php

namespace ride\library\orm\model\behaviour\initializer;

use ride\library\generator\CodeClass;
use ride\library\generator\CodeGenerator;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\behaviour\SlugBehaviour;
use ride\library\reflection\Boolean;

use \InvalidArgumentException;

/**
 * Setup the slug behaviour based on the model options
 */
class SlugBehaviourInitializer implements BehaviourInitializer {

    /**
     * Gets the behaviours for the model of the provided model table
     * @param \ride\library\orm\definition\ModelTable $modelTable
     * @return array An array with instances of Behaviour
     * @see \ride\library\orm\model\behaviour\Behaviour
     */
    public function getBehavioursForModel(ModelTable $modelTable) {
        if (!$modelTable->getOption('behaviour.slug')) {
            return array();
        }

        if (!$modelTable->hasField('slug')) {
            $slugField = new PropertyField('slug', 'string');
            $slugField->setIsUnique(true);
            $slugField->setOptions(array(
                'label.name' => 'label.slug',
                'scaffold.form.omit' => 'true',
            ));
            $slugField->addValidator('required', array());

            $modelTable->addField($slugField);
        }

        return array(new SlugBehaviour());
    }

    /**
     * Generates the needed code for the entry class of the provided model table
     * @param \ride\library\orm\definition\ModelTable $table
     * @param \ride\library\generator\CodeGenerator $generator
     * @param \ride\library\generator\CodeClass $class
     * @return null
     */
    public function generateEntryClass(ModelTable $modelTable, CodeGenerator $generator, CodeClass $class) {
        $slugValue = $modelTable->getOption('behaviour.slug');
        if (!$slugValue) {
            return;
        }

        $class->addImplements('ride\\library\\orm\\entry\\SluggedEntry');

        try {
            Boolean::getBoolean($slugValue);

            return;
        } catch (InvalidArgumentException $exception) {

        }

        $properties = explode(',', $slugValue);

        $slugCode = "\$slug = '';\n";
        foreach ($properties as $property) {
            $property = trim($property);
            $tokens = explode('.', $property);

            $var = '$this';
            foreach ($tokens as $token) {
                $var .= '->get' . ucfirst($token) . '()';
            }

            $slugCode .= '$slug .= \' \' . ' . $var . ";\n";
        }
        $slugCode .= "\nreturn trim(\$slug);";

        $slugMethod = $generator->createMethod('getSlugBase', array(), $slugCode);
        $slugMethod->setDescription('Gets the desired slug based on properties of the entry');
        $slugMethod->setReturnValue($generator->createVariable('result', 'string'));

        $class->addMethod($slugMethod);
    }

}
