<?php
// UNTESTED !!!!

class SEInternationalisation_SnippetController extends Admin_DocumentController {
	
	public function saveAction() {
		if ($this->_getParam("id")) {
			$languages = (array) Pimcore_Tool::getValidLanguages();
			if ($this->_validateSave($languages)) {
				$saved = false;
				foreach ($languages as $language) {
						
					$snippet = $this->_getSnippet(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
					$snippet = $this->getLatestVersion($snippet);
			
					$snippet->getPermissionsForUser($this->getUser());
					$snippet->setUserModification($this->getUser()->getId());
			
					// save to session
					$key = "document_" . $this->_getParam("id");
					$session = new Zend_Session_Namespace("pimcore_documents");
					$session->$key = $snippet;
			
			
					if ($this->_getParam("task") == "unpublish") {
						$snippet->setPublished(false);
					}
					if ($this->_getParam("task") == "publish") {
						$snippet->setPublished(true);
					}
			
			
					if (($this->_getParam("task") == "publish" && $snippet->isAllowed("publish")) or ($this->_getParam("task") == "unpublish" && $snippet->isAllowed("unpublish"))) {
						$this->setValuesToDocument($snippet);
			
						try {
							$snippet->save();
							$saved = true;
						} catch (Exception $e) {
							$this->_helper->json(array("success" => false, "message" => $e->getMessage()));
						}
			
			
					}
					else {
						if ($snippet->isAllowed("save")) {
							$this->setValuesToDocument($snippet);
			
							try {
								$snippet->saveVersion();
								$saved = true;
							} catch (Exception $e) {
								$this->_helper->json(array("success" => false, "message" => $e->getMessage()));
							}

						}
					}
				}
				if ($saved) {
					$this->_helper->json(array("success" => true));
				}
			}
		}
	
		$this->_helper->json(false);
	}
	
	protected function _validateSave($languages) {
		$allowed = false;
		$notAllowed = false;
		foreach ($languages as $language) {
			$page = $this->_getSnippet(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
//			$page->getPermissionsForUser($this->getUser());
			if (($this->_getParam("task") == "publish" && $page->isAllowed("publish")) or ($this->_getParam("task") == "unpublish" && $page->isAllowed("unpublish"))) {
				$allowed = true;
			} else {
				if ($page->isAllowed("save")) {
					$allowed = true;
				} else {
					$notAllowed = true;
				}
			}
		}
		return $allowed && !$notAllowed;
	}
	
	protected function _getSnippet($document_id) {
		if (!isset($this->_cache["snippet"][$document_id])) {
			$doc = Document_Snippet::getById($document_id);
			$this->_cache["snippet"][$document_id] = $doc;
		}
		return $this->_cache["snippet"][$document_id];
	}
}