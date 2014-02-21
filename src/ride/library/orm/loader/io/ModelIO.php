<?php

namespace ride\library\orm\loader\io;

/**
 * Interface to read and write model definitions
 */
interface ModelIO {

    /**
     * Read models from the data source
     * @return array Array with the name of the model as key and an instance of
     * Model as value
     * @see ride\library\orm\model\Model
     */
    public function readModels();

    /**
     * Write the models to the data source
     * @param array $models Array with the name of the model as key and an
     * instance of Model as value
     * @return null
     * @see ride\library\orm\model\Model
     */
    public function writeModels(array $models);

}