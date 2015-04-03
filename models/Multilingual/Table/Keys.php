<?php

namespace Multilingual\Table;

class Keys extends \Zend_Db_Table_Abstract
{
    protected $_name = 'plugin_multilingual_keys';
    protected $_primary = array("document_id");

}