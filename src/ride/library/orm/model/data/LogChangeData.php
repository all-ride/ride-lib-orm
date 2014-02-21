<?php

namespace ride\library\orm\model\data;

/**
 * Data container for the log of a model action
 */
class LogChangeData extends Data {

    /**
     * Name of the data model
     * @var string
     */
    public $dataModel;

    /**
     * Primary key of the data
     * @var integer
     */
    public $dataId;

    /**
     * Version of the data
     * @var integer
     */
    public $dataVersion;

    /**
     * Name of the action
     * @var string
     */
    public $action;

    /**
     * Array with the changes to the data
     * @var array
     */
    public $changes;

    /**
     * User who initiated the action of this log
     * @var string
     */
    public $user;

}