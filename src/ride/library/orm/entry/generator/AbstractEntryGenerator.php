<?php

namespace ride\library\orm\entry\generator;

use ride\library\generator\CodeGenerator;
use ride\library\system\file\browser\FileBrowser;

/**
 * Entry generate for the general entry class
 */
abstract class AbstractEntryGenerator implements EntryGenerator {

    /**
     * Constructs a new entry generator
     * @param \ride\library\system\file\browser\FileBrowser $fileBrowser
     * @param \ride\library\generator\CodeGenerator $codeGenerator
     * @param string $defaultNamespace Namespace for the generic entries
     * @return null
     */
    public function __construct(FileBrowser $fileBrowser, CodeGenerator $generator, $defaultNamespace = 'ride\\application\\orm\\entry') {
        $this->generator = $generator;
        $this->fileBrowser = $fileBrowser;
        $this->defaultNamespace = $defaultNamespace;
    }

    protected function normalizeType($type) {
        switch ($type) {
            case 'binary':
            case 'pk':
            case 'fk':
            case 'email':
            case 'website':
            case 'text':
            case 'wysiwyg':
            case 'file':
            case 'image':
            case 'password':
                return 'string';
            case 'date':
            case 'datetime':
                return 'integer';
            case 'serialize':
                return 'mixed';
        }

        return $type;
    }

}
