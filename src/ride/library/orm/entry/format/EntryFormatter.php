<?php

namespace ride\library\orm\entry\format;

/**
 * Interface for a model entry formatter
 */
interface EntryFormatter {

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
     * Name of the date format
     * @var string
     */
    const FORMAT_DATE = 'date';

    /**
     * Formats the entry
     * @param mixed $entry Entry instance
     * @param string $format Format string
     * @return mixed A formatted version of the entry
     */
    public function formatEntry($entry, $format);

}
