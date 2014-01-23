<?php
// UNTESTED !!!!
class SEInternationalisation_PageController extends Admin_DocumentController {

	protected $_cache = array();
		
	public function saveAction() {
	
		if ($this->_getParam("id")) {
			$languages = (array) Pimcore_Tool::getValidLanguages();
			if ($this->_validateSave($languages)) {
				$saved = false;
				foreach ($languages as $language) {
					
				
					$page = $this->_getPage(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
			
					$page = $this->getLatestVersion($page);
			
//					$page->getPermissionsForUser($this->getUser());
					$page->setUserModification($this->getUser()->getId());
			
					// save to session
					$key = "document_" . $this->_getParam("id")."_".$language;			// waarom wordt dat hier in de sessie gestoken ?????????
					$session = new Zend_Session_Namespace("pimcore_documents");
					$session->$key = $page;
			
					if ($this->_getParam("task") == "unpublish") {
						$page->setPublished(false);
					}
					if ($this->_getParam("task") == "publish") {
						$page->setPublished(true);
					}
			
					// only save when publish or unpublish
					if (($this->_getParam("task") == "publish" && $page->isAllowed("publish")) or ($this->_getParam("task") == "unpublish" && $page->isAllowed("unpublish"))) {
						$this->setValuesToDocument($page);
			
						try{
							$page->save();
							$saved = true;
						} catch (Exception $e) {
							logger::err($e);
							$this->_helper->json(array("success" => false,"message"=>$e->getMessage()));
						}
			
					}
					else {
						if ($page->isAllowed("save")) {
							$this->setValuesToDocument($page);
			
							try{
								$page->saveVersion();
								$saved = true;
							} catch (Exception $e) {
								logger::err($e);
								$this->_helper->json(array("success" => false,"message"=>$e->getMessage()));
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
			$page = $this->_getPage(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
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
	
	protected function _getPage($document_id) {
		if (!isset($this->_cache["page"][$document_id])) {
			$doc = Document_Page::getById($document_id);
			$this->_cache["page"][$document_id] = $doc;
		}
		return $this->_cache["page"][$document_id];
	}
	
	protected function getLatestVersion (Document $document) {
	
		$latestVersion = $document->getLatestVersion();
		if($latestVersion) {
			$latestDoc = $latestVersion->loadData();
			if($latestDoc instanceof Document) {
				$latestDoc->setModificationDate($document->getModificationDate()); // set de modification-date from published version to compare it in js-frontend
				return $latestDoc;
			}
		}
		return $document;
	}
	
	protected function setValuesToDocument(Document $page) {
	
		$this->addSettingsToDocument($page);
		$this->addDataToDocument($page);
		$this->addPropertiesToDocument($page);
		$this->addSchedulerToDocument($page);
	}
	
	protected function addSettingsToDocument(Document $document) {
	
		// settings
		if ($this->_getParam("settings")) {
			if ($document->isAllowed("settings")) {
				$settings = Zend_Json::decode($this->_getParam("settings"));
				$document->setValues($settings);
			}
		}
	}
	
	protected function addDataToDocument(Document $document) {
	
		// data
		if ($this->_getParam("data")) {
			$data = Zend_Json::decode($this->_getParam("data"));
			foreach ($data as $name => $value) {
				$data = $value["data"];
				$type = $value["type"];
				$document->setRawElement($name, $type, $data);
			}
		}
	}
	
	protected function addPropertiesToDocument(Document $document) {
	
		// properties
		if ($this->_getParam("properties")) {
	
			$properties = array();
			// assign inherited properties
			foreach ($document->getProperties() as $p) {
				if ($p->isInherited()) {
					$properties[$p->getName()] = $p;
				}
			}
	
			$propertiesData = Zend_Json::decode($this->_getParam("properties"));
	
			if (is_array($propertiesData)) {
				foreach ($propertiesData as $propertyName => $propertyData) {
	
					$value = $propertyData["data"];
	
					try {
						$property = new Property();
						$property->setType($propertyData["type"]);
						$property->setName($propertyName);
						$property->setCtype("document");
						$property->setDataFromEditmode($value);
						$property->setInheritable($propertyData["inheritable"]);
	
						$properties[$propertyName] = $property;
					}
					catch (Exception $e) {
						logger::warning("Can't add " . $propertyName . " to document " . $document->getFullPath());
					}
	
				}
			}
			if ($document->isAllowed("properties")) {
				$document->setProperties($properties);
			}
		}
	
		// force loading of properties
		$document->getProperties();
	}
	
	protected function addSchedulerToDocument(Document $document) {
	
		// scheduled tasks
		if ($this->_getParam("scheduler")) {
			$tasks = array();
			$tasksData = Zend_Json::decode($this->_getParam("scheduler"));
	
			if (!empty($tasksData)) {
				foreach ($tasksData as $taskData) {
					$taskData["date"] = strtotime($taskData["date"] . " " . $taskData["time"]);
	
					$task = new Schedule_Task($taskData);
					$tasks[] = $task;
				}
			}
	
			if ($document->isAllowed("settings")) {
				$document->setScheduledTasks($tasks);
			}
		}
	}
	
	
}