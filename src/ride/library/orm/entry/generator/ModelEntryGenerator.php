<?php

namespace ride\library\orm\entry\generator;

use ride\library\orm\loader\ModelRegister;
use ride\library\orm\meta\ModelMeta;
use ride\library\system\file\File;

/**
 * Entry generator for the defined model entry class
 */
class ModelEntryGenerator extends AbstractEntryGenerator {

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
        $modelMeta = $model->getMeta();

        $entryClassName = $modelMeta->getEntryClassName();
        if ($entryClassName == ModelMeta::CLASS_ENTRY) {
            return;
        }

        $classFileName = str_replace('\\', '/', $entryClassName) . '.php';
        $sourceFile = $this->fileBrowser->getFile('src/' . $classFileName);
        if ($sourceFile) {
            return;
        }

        $sourceFile = $sourcePath->getChild($classFileName);
        if ($sourceFile->exists()) {
            return;
        }

        $class = $this->generator->createClass($entryClassName, $this->defaultNamespace . '\\' . $modelName . 'Entry');

        $sourceFile->write($this->generator->generateClass($class));
    }

}
