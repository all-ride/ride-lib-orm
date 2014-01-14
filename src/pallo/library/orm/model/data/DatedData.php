<?php

namespace pallo\library\orm\model\data;

interface DatedData {

    public function setDateAdded($timestamp = null);

    public function getDateAdded();

    public function setDateModified($timestamp = null);

    public function getDateModified();

}