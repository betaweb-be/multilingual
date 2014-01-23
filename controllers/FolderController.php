<?php
/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @copyright  Copyright (c) 2009-2010 elements.at New Media Solutions GmbH (http://www.elements.at)
 * @license    http://www.pimcore.org/license     New BSD License
 */

class SEInternationalisation_FolderController extends Admin_FolderController {

     protected $_cache = array();
     
	 public function saveAction() {
        if ($this->_getParam("id")) {
        	$languages = (array) Pimcore_Tool::getValidLanguages();
        	if ($this->_validateSave($languages)) {
        		$saved = false;
        		foreach ($languages as $language) {

            		$folder = $this->_getFolder(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
//            		$folder->getPermissionsForUser($this->getUser());

            		$folder->setModificationDate(time());
            		$folder->setUserModification($this->getUser()->getId());

		            if ($folder->isAllowed("publish")) {
		                $this->setValuesToDocument($folder);
		                $folder->save();
		
		            }
        		}
        		$this->_helper->json(array("success" => true));
        	}
        }
        $this->_helper->json(false);
    }
    
    protected function _validateSave($languages) {
    	$allowed = false;
    	$notAllowed = false;
    	foreach ($languages as $language) {
    		$folder = $this->_getFolder(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
//    		$folder->($this->getUser());
    		if (!$folder->isAllowed("publish")) {
    			$allowed = true;
    		} else {
    			$notAllowed = true;
    		}
    	}
    	return $allowed && !$notAllowed;
    }
    
    protected function _getFolder($document_id) {
    	if (!isset($this->_cache["folder"][$document_id])) {
    		$doc = Document_Folder::getById($document_id);
    		$this->_cache["folder"][$document_id] = $doc;
    	}
    	return $this->_cache["folder"][$document_id];
    }

    
}
