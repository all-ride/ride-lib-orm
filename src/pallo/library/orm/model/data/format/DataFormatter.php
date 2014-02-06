<?php

namespace pallo\library\orm\model\data\format;

use pallo\library\reflection\ReflectionHelper;

/**
 * Formatter for model data
 */
class DataFormatter {

    /**
     * Name of the title format
     * @var string
     */
    const FORMAT_TITLE = 'title';

    /**
     * Name of the teaser format
     * @var string
     */
    const FORMAT_TEASER = 'teaser';

    /**
     * Name of the image format
     * @var string
     */
    const FORMAT_IMAGE = 'image';

    /**
     * Name of the image format
     * @var string
     */
    const FORMAT_DATE = 'date';

    /**
     * Instance of the reflection helper
     * @var pallo\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Used data formats
     * @var array
     */
    private $formats;

    /**
     * Construct a new data formatter
     * @param pallo\library\reflection\ReflectionHelper $reflectionHelper
     * @param array $modifiers Available modifiers
     * @return null;
     */
    public function __construct(ReflectionHelper $reflectionHelper, array $modifiers) {
        $this->reflectionHelper = $reflectionHelper;
        $this->modifiers = $modifiers;
        $this->formats = array();
    }

    /**
     * Format the data with the provided format
     * @param mixed $data Model data object
     * @param string $format The format string
     * @return mixed A human readable string or a certain value of the data
     */
    public function formatData($data, $format) {
        if (!isset($this->formats[$format])) {
            $this->formats[$format] = new DataFormat($this->reflectionHelper, $format, $this->modifiers);
        }

        return $this->formats[$format]->formatData($data);
    }

}