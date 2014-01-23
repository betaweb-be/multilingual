<?php
/**
 * Contact Model
 * 
 * Handles the info of the contact form
 * @author meyvaertp
 *
 */
Zend_Db_Table::setDefaultAdapter(Pimcore_Resource_Mysql::get()->getResource());
class SEInternationalisation_Table_Keys extends Zend_Db_Table_Abstract {
	 protected $_name = 'se_i18n_keys';
	 protected $_primary = array("document_id");
	 
}