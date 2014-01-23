<?php
/**
 * Contact Model
 * 
 * Handles the info of the contact form
 * @author meyvaertp
 *
 */
Zend_Db_Table::setDefaultAdapter(Pimcore_Resource_Mysql::get()->getResource());
class SEInternationalisation_Table_Properties extends Zend_Db_Table_Abstract {
	 protected $_name = 'properties';
	 protected $_primary = array("cid", "ctype", "name");
	 
}