<?php
// UNTESTED !!!!
class SEInternationalisation_HardlinkController extends Admin_DocumentController {
	
	protected $_cache = array();
	 
	public function saveAction() {
		if ($this->_getParam("id")) {
			
			$languages = (array) Pimcore_Tool::getValidLanguages();
			if ($this->_validateSave($languages)) {
				
				foreach ($languages as $language) {
							
					$link = $this->_getHardLink(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
					
//					$link->getPermissionsForUser($this->getUser());
			
					$this->setValuesToDocument($link);
			
					$link->setModificationDate(time());
					$link->setUserModification($this->getUser()->getId());
			
					if ($this->_getParam("task") == "unpublish") {
						$link->setPublished(false);
					}
					if ($this->_getParam("task") == "publish") {
						$link->setPublished(true);
					}
			
					// only save when publish or unpublish
					if (($this->_getParam("task") == "publish" && $link->isAllowed("publish")) || ($this->_getParam("task") == "unpublish" && $link->isAllowed("unpublish"))) {
						$link->save();
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
			$link = $this->_getHardLink(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
//			$link->getPermissionsForUser($this->getUser());
			if (!(($this->_getParam("task") == "publish" && $link->isAllowed("publish")) || ($this->_getParam("task") == "unpublish" && $link->isAllowed("unpublish")))) {
				$allowed = true;
			} else {
				$notAllowed = true;
			}
		}
		return $allowed && !$notAllowed;
	}
	
	protected function _getHardLink($document_id) {
		if (!isset($this->_cache["hardlink"][$document_id])) {
			$doc = Document_Hardlink::getById($document_id);
			$this->_cache["hardlink"][$document_id] = $doc;
		}
		return $this->_cache["hardlink"][$document_id];
	}
	
}