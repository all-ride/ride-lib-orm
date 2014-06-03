<?php

namespace ride\library\orm\entry\generator;

use ride\library\orm\loader\ModelRegister;
use ride\library\system\file\File;

/**
 * Interface to generate a entry class
 */
interface EntryGenerator {

    /**
     * Generates an entry class for the provided model
     * @param \ride\library\orm\loader\ModelRegister $modelRegister Instance of
     * the model register
     * @param string $modelName Name of the model to generate an entry for
     * @param \ride\library\system\file\File $sourcePath Path of the source
     * directory where the class will be generated
     * @return null
     */
    public function generate(ModelRegister $modelRegister, $modelName, File $sourcePath);

}
