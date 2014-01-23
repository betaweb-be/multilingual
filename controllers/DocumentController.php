<?php

class SEInternationalisation_DocumentController extends Admin_DocumentController {
	
/**
 * Original function
 * @see Admin_DocumentController::treeGetChildsByIdAction()
 */
	protected $_cache = array(); 
	
	public function copytoallAction() {
		$currentId = $this->_getParam("id");		
		$masterDoc = Document::getById($currentId);
		$els = $masterDoc->getElements();
		
		// Rebuild current cache
		$key = "document_" . $currentId;
		$session = new Zend_Session_Namespace("pimcore_documents");
		$session->$key = $masterDoc;
		Pimcore_Model_Cache::clearTag("document_" . $currentId);
		
		// Determine witch fields to copy to all languages
		$controller = $masterDoc->getController();
		$action = $masterDoc->getAction();
		$path = PIMCORE_WEBSITE_PATH . '/config/documents/'.$controller.'/'.$action.'.xml';
		if (is_file($path)) {
			$elementConfig = new Zend_Config_Xml($path);
			if ($elementConfig->elements) {
				foreach ($elementConfig->elements as $key=>$value) {
					if ($value == 1) {
						$elementsToCopy[$key] = $value;
					}
				}
			} else {
				$elementsToCopy = $els;
			}
		} else {
			$elementsToCopy = $els;
		}
		
		// Loop document in all languages
		$languages = (array) Pimcore_Tool::getValidLanguages();
		$documents = array();
		foreach ($languages as $language) {
			try {
				$docId = SEInternationalisation_Document::getDocumentIdInOtherLanguage($currentId, $language);
				
 				if ($docId != $currentId) {
					$documents[] = $docId;
 					$doc = Document::getById($docId);
					$doc->getElements();
					$doc->getResource()->deleteAllElements();
					
					// Fix so multihref elements are not lost on save
					foreach($doc->getElements() as $key=>$el) {
						if ($el instanceof Document_Tag_Multihref) {
							$el->load();
						}
					}
					
					foreach ($els as $key=>$el) {
						if ($el instanceof Document_Tag_Multihref) {
							$el->load();
						}
						$el->setDocumentId($docId);
						if (array_key_exists($key,$elementsToCopy)) {
							$doc->setElement($key,$el);
// 							echo "key directly found: " . $key . "\n";
						} else {
							foreach ($elementsToCopy as $k=>$e) {
								if (preg_match("/^(.*)" . $k . "+[0-9]/", $key)) {
									$doc->setElement($key,$el);
// 									echo "regex ($k) found for key: " . $key . "\n";
								}
							}
						}
					}
					$doc->doNotSyncProps = true;
					$doc->save();
					
					// save to session
					$key = "document_" . $doc->getId();
					$session = new Zend_Session_Namespace("pimcore_documents");
					$session->$key = $doc;
					Pimcore_Model_Cache::clearTag("document_" . $doc->getId());
					$this->_cache["document"][$doc->getId()] = $doc;
 				}
				
			} catch (Exception $e) {
				print_r($e);
				$this->_helper->json(array("success"=>false));
			}   
		}
		Pimcore_Model_Cache::clearAll();
		$this->_helper->json(array("success"=>true,"documents"=>$documents));
	}
	

	public function treeGetChildsByIdAction() {
		$languages = (array) Pimcore_Tool::getValidLanguages();
		$language = $this->_getParam("language", reset($languages));
		$node = $this->_getParam("node");
		$document = Document::getById($node);
	
		$documents = array();
		if ($document->hasChilds()) {
			$limit = intval($this->_getParam("limit"));
			if (!$this->_getParam("limit")) {
				$limit = 100000000;
			}
			$offset = intval($this->_getParam("start"));
	
	
			$allowedIds = array();
			if ($node == 1 && !is_null($language)) {
				$t = new SEInternationalisation_Table_Properties();
				$select = $t->select()->where("name = 'language'")->where("type = 'text'")->where("data = ?", $language);
				$rows = $t->fetchAll($select);
				foreach ($rows as $row) {
					$allowedIds[] = (int)$row->cid;
				}
			}
			$list = new Document_List();
			if (!empty($allowedIds) && $node == 1) {
				$list->setCondition("parentId = ".(int)$document->getId()." AND id IN (?)", implode(",", $allowedIds));
			} else {
				$list->setCondition("parentId = ?", (int)$document->getId());				
			}
			$list->setOrderKey("index");
			$list->setOrder("asc");
			$list->setLimit($limit);
			$list->setOffset($offset);
			$childsList = $list->load();
	
			foreach ($childsList as $childDocument) {
				// get current user permissions
//				$childDocument->getPermissionsForUser($this->getUser());
				// only display document if listing is allowed for the current user
				if ($childDocument->isAllowed("list")) {
					$config = $this->getTreeNodeConfig($childDocument);
					if ($node == 1) {
						$config["expanded"] = true;
					}
					$documents[] = $config;
				}
				 
			}
				
		}
	
		if ($this->_getParam("limit")) {
			$this->_helper->json(array(
					"total" => $document->getChildAmount(),
					"nodes" => $documents
			));
		}
		else {
			$this->_helper->json($documents);
		}
	
		$this->_helper->json(false);
	}
	
	public function getRelatedDocumentsAction() {
		$currentLanguage = $this->_getParam("language");
		$currentId = $this->_getParam("id");
		$languages = (array) Pimcore_Tool::getValidLanguages();
		
		$documents = array();
		foreach ($languages as $language) {
			try {
				$docId = SEInternationalisation_Document::getDocumentIdInOtherLanguage($currentId, $language);
				$doc = Document::getById($docId);
				$documents[$language] = $doc->getKey();
			} catch (Exception $e) {
				$this->_helper->json(array("success" => false, "message" => $e->getMessage()));
			}   
		}
	
		if ($documents) {
			$this->_helper->json(array("success" => true, "data" => $documents));
		} else {
			$this->_helper->json(array("success" => false, "message" => 'Could not find any related documents'));
		}
	}

	public function addAction() {
		// todo: TESTEN
		$languages = (array) Pimcore_Tool::getValidLanguages();
		$currentLanguage = $this->_getParam("language");
		$keys = array();
		$parents = array();
		foreach ($languages as $language) {
			$keys[$language] = $this->_getParam("key_".$language);
			try {
				$parents[$language] = SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("parentId"), $language);
			} catch (Exception $e) {
				$this->_helper->json(array("success" => false, "message" => $e->getMessage()));
			}
		}
		
		// 1. per taal: checken of alles goed is (indien in 1 taal niet goed => error)
		// check key, check type, check perms
		
		if ($this->_validateAddDocument($languages, $keys, $parents)) {
			
			// 2. alle documenten aanmaken + overal taal goedzetten.
			$sourcePath = null;
			$ts = date('His');
			$sourcePath = md5($ts . $sourcePath);
			foreach ($languages as $language) {
				
				
				$doc = $this->_addDocument($parents[$language], $language, $keys[$language], $sourcePath);
				if (is_null($sourcePath)) {
					$sourcePath = $doc->getFullPath();
				}
				if ($language == $currentLanguage) {
					$document = $doc;
				}
			}
			if ($document) {
				$this->_helper->json(array(
						"success" => true,
						"id" => $document->getId(),
						"type" => $document->getType()
				));
			} else {
				$this->_helper->json(array(
					"success" => true,
				));				
			}
		} else {
			// error: revise
			$this->_helper->json(array(
					"success" => false,
					"message" => "not all documents were valid",  // to do => appropriate message
			));
		}
		
	}
	
	protected function _getDocument($document_id) {
		if (!isset($this->_cache["document"][$document_id])) {
			$doc = Document::getById($document_id);
			$this->_cache["document"][$document_id] = $doc;
		}
		return $this->_cache["document"][$document_id];
	}
	
	protected function _getDocumentByPath($path) {
		if (!isset($this->_cache["documentByPath"][$path])) {
			$doc = Document::getByPath($path);
			$this->_cache["documentByPath"][$path] = $doc;
		}
		return $this->_cache["documentByPath"][$path];
	}
	
	protected function _getDocType($doctype_id) {
		if (!isset($this->_cache["doctype"][$doctype_id])) {
			$docType = Document_DocType::getById(intval($doctype_id));
			$this->_cache["doctype"][$doctype_id] = $docType;
		}
		return $this->_cache["doctype"][$doctype_id];
	}
	
	public function _validateAddDocument($languages, $keys, $parents) {
		$allowedTypes = array("page", "snippet", "link", "hardlink", "folder","email");
		$success = false;
		if (in_array($this->_getParam("type"), $allowedTypes)) {
			
			$success = true;
			foreach ($languages as $language) {
				if (isset($keys[$language]) && isset($parents[$language])) {
					$parentDocument = $this->_getDocument(intval($parents[$language]));
//					$parentDocument->getPermissionsForUser($this->getUser());
					if ($parentDocument->isAllowed("create")) {
						$intendedPath = $parentDocument->getFullPath() . "/" . $keys[$language];
						$equalDocument = Document::getByPath($intendedPath);
						if ($equalDocument == null) {
							// ok
						} else {
							$success = false;
						}
					} else {
						$success = false;
					}		
				} else {
					$success = false;
				}
			}
		}
		return $success;
	}
	
	public function _addDocument($parentId, $language, $key, $sourcePath) {
		$createValues = array(
				"userOwner" => $this->getUser()->getId(),
				"userModification" => $this->getUser()->getId(),
				"published" => false
		);

		$createValues["key"] = $key;

		// check for a docType
		if ($this->_getParam("docTypeId") && is_numeric($this->_getParam("docTypeId"))) {
			$docType = $this->_getDocType(intval($this->_getParam("docTypeId")));
			$createValues["template"] = $docType->getTemplate();
			$createValues["controller"] = $docType->getController();
			$createValues["action"] = $docType->getAction();
		} else if($this->_getParam("type") == "page" || $this->_getParam("type") == "snippet") {
			$createValues["controller"] = Pimcore_Config::getSystemConfig()->documents->default_controller;
			$createValues["action"] = Pimcore_Config::getSystemConfig()->documents->default_action;
		}

		switch ($this->_getParam("type")) {
			case "page":
				$document = Document_Page::create($parentId, $createValues);
				$success = true;
				break;
			case "snippet":
				$document = Document_Snippet::create($parentId, $createValues);
				$success = true;
				break;
			case "email":
				$document = Document_Email::create($parentId, $createValues);
				$success = true;
				break;
			case "link":
				$document = Document_Link::create($parentId, $createValues);
				$success = true;
				break;
			case "hardlink":
				$document = Document_Hardlink::create($parentId, $createValues);
				$success = true;
				break;
			case "folder":
				$document = Document_Folder::create($parentId, $createValues);
				$document->setPublished(true);
				try {
					$document->save();
					$success = true;
				} catch (Exception $e) {
					$this->_helper->json(array("success" => false, "message" => $e->getMessage()));		// dit is nog een probleem indien er bij de ene taal een error optreedt en bij de andere niet. Kan dit? Onderzoeken!
				}
				break;
		}
		if ($document) {
			// ook nog de taal zetten !!
			$document->setProperty("language", "text", $language);
			if($document instanceof Document_Page) {
				$humanReadableKey = ucfirst(str_replace('-',' ',$key));
				$document->setProperty('navigation_name','text',$humanReadableKey);
				$document->setProperty('navigation_title','text',$humanReadableKey);
				$document->setTitle($humanReadableKey);
			}
			if ($document instanceof Document_Email) {
				$document->setController('email');
			}
			$document->save(); //is dit nodig?
			$t = new SEInternationalisation_Table_Keys();
			if (is_null($sourcePath)) {
				$sourcePath = $document->getFullPath();
			}
			$data = array(
					"document_id" => $document->getId(),
					"language" => $language,
					"sourcePath" => $sourcePath,
					);
			$t->insert($data);
			// 
			
			return $document;
		} else {
			return null;
		}
	}
	
	public function deleteInfoAction() {
		$languages = (array) Pimcore_Tool::getValidLanguages();
		$deleteJobs = array();
		
		foreach ($languages as $language) {
			$hasDependency = false;

			try {
				$document = Document::getById(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
				//die(print_r($document,true));
				$hasDependency = $document->getDependencies()->isRequired();
			}
			catch (Exception $e) {
				logger::err("failed to access document with id: " . $this->_getParam("id") . " in language: " . $language);
			}
		
			// check for childs
			if($document instanceof Document) {
				$hasChilds = $document->hasChilds();
				if (!$hasDependency) {
					$hasDependency = $hasChilds;
				}
		
				$childs = 0;
				if($hasChilds) {
					// get amount of childs
					$list = new Document_List();
					$list->setCondition("path LIKE '" . $document->getFullPath() . "/%'");
					$childs = $list->getTotalCount();
		
					if($childs > 0) {
						$deleteObjectsPerRequest = 5;
						for($i=0; $i<ceil($childs/$deleteObjectsPerRequest); $i++) {		
							$deleteJobs[] = array(array(
									"url" => "/plugin/SEInternationalisation/document/delete",
									"params" => array(
											"step" => $i,
											"amount" => $deleteObjectsPerRequest,
											"type" => "childs",
											"id" => $document->getId()
									)
							));
						}
					}
				}
		
				// the object itself is the last one
				$deleteJobs[] = array(array(
						"url" => "/plugin/SEInternationalisation/document/delete",
						"params" => array(
								"id" => $document->getId()
						)
				));
			}
		
		}
		
		
		$this->_helper->json(array(
				"hasDependencies" => $hasDependency,
				"childs" => $childs,
				"deletejobs" => $deleteJobs
		));
	}
	
	
	// partially untested !!!
	public function deleteAction()
	{
		$languages = (array) Pimcore_Tool::getValidLanguages();
		
		if ($this->_getParam("type") == "childs") {		// untested
	
			foreach ($languages as $language) {
				
				$parentDocument = $this->_getDocument($this->_getParam("id"));
		
				$list = new Document_List();
				$list->setCondition("path LIKE '" . $parentDocument->getFullPath() . "/%'");
				$list->setLimit(intval($this->_getParam("amount")));
				$list->setOrderKey("LENGTH(path)", false);
				$list->setOrder("DESC");
		
				$documents = $list->load();
		
				$deletedItems = array();
				foreach ($documents as $document) {
					$deletedItems[] = $document->getFullPath();
					$document->delete();
					
					$t = new SEInternationalisation_Table_Keys();
					$where = $t->getAdapter()->quoteInto("document_id = ?",$document->getId());
					$t->delete($where);
					
				}
			}
			$this->_helper->json(array("success" => true, "deleted" => $deletedItems));
	
		} else if($this->_getParam("id")) {		// seems ok
			$allowed = true;
			$document = Document::getById($this->_getParam("id"));
//			$document->getPermissionsForUser($this->getUser());
			if (!$document->isAllowed("delete")) {
				$allowed = false;
			}				
			if ($allowed) {
				$document = Document::getById($this->_getParam("id"));
				Element_Recyclebin_Item::create($document, $this->getUser());
				$document->delete();
				$t = new SEInternationalisation_Table_Keys();
				$where = $t->getAdapter()->quoteInto("document_id = ?",$document->getId());
				$t->delete($where);
				
				$this->_helper->json(array("success" => true));
			}
		}
	
		$this->_helper->json(array("success" => false, "message" => "missing_permission"));
	}

	/**
	 * Get available frontend languages
	 */
	public function getAvailableLanguagesAction() {
		$languages = (array) Pimcore_Tool::getValidLanguages();
		
		$return = array();
		if ($languages) {
			foreach ($languages as $language) {
				$return[] = array('id'=> $language, 'language'=> $language);
			}
		}
		
		$this->_helper->json($return);

	}

	/**
	 * Get available frontend languages
	 */
	public function getAllDocumentsAction() {
		$languages = (array) Pimcore_Tool::getValidLanguages();
		$id = $this->getParam('id');

		$return = array();
		if ($languages) {
			foreach ($languages as $language) {
				if ($language !== CURRENT_LANGUAGE) {
					try {
						$otherLangId = SEInternationalisation_Document::getDocumentIdInOtherLanguage($id, $language);
					}catch (Exception $e) {	}
					if ($otherLangId) {
						$return[] = array('id'=> $otherLangId, 'language'=> $language);
					}
				}
			}
		}

		$this->_helper->json($return);

	}
	
	public function updateAction() {
		
		$languages = (array) Pimcore_Tool::getValidLanguages();
		$success = false;
		$blockedVars = array("controller", "action", "module", "language", "id");
        foreach ($languages as $language) {
        	$blockedVars[] = "key_".$language;
        }
        foreach ($languages as $language) {

        	$this->_setParam("key", $this->_getParam("key_".$language));

        	$success = false;
	        $allowUpdate = true;

	        $document = $this->_getDocument(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("id"), $language));
	
	        // check for permissions
//	        $document->getPermissionsForUser($this->getUser());
	       
	        if ($document->isAllowed("settings")) {
	
	            // if the position is changed the path must be changed || also from the childs
	            if ($this->getParam("parentId")) {
	                $parentDocument = $this->_getDocument(SEInternationalisation_Document::getDocumentIdInOtherLanguage($this->_getParam("parentId"), $language));
	                $this->setParam("parentId", $parentDocument->getId());

	                //check if parent is changed
	                if ($document->getParentId() != $parentDocument->getId()) {
	
	                    if(!$parentDocument->isAllowed("create")){
	                        throw new Exception("Prevented moving document - no create permission on new parent ");
	                    }
	
	                    $intendedPath = $parentDocument->getPath();
	                    $pKey = $parentDocument->getKey();
	                    if (!empty($pKey)) {
	                        $intendedPath .= $parentDocument->getKey() . "/";
	                    }
	
	                    $documentWithSamePath = $this->_getDocumentByPath($intendedPath . $document->getKey());
	
	                    if ($documentWithSamePath != null) {
	                        $allowUpdate = false;
	                    }
	                }
	            }
	
	            if ($allowUpdate) {
	                if ($this->_getParam("key_" . $language) || $this->_getParam("parentId")) {
	                    $oldPath = $document->getPath() . $document->getKey();
	                 }
	
	                if(!$document->isAllowed("rename") && $this->_getParam("key_" . $language)){
	                    $blockedVars[]="key";
	                    Logger::debug("prevented renaming document because of missing permissions ");
	                } 
	                
	                foreach ($this->getAllParams() as $key => $value) {
	                    if (!in_array($key, $blockedVars)) {
//	                    	print_r("Document_id: ".$document->getId()." set: ".$key.": ".$value);
	                        $document->setValue($key, $value);
	                    }
	                }
	
	                // if changed the index change also all documents on the same level
	                if ($this->_getParam("index") !== null) {
	                    $list = new Document_List();
	                    $list->setCondition("parentId = ? AND id != ?", array($parentDocument->getId(), $document->getId()));
	                    $list->setOrderKey("index");
	                    $list->setOrder("asc");
	                    $childsList = $list->load();
	
	                    $count = 0;
	                    foreach ($childsList as $child) {
	                        if ($count == intval($this->_getParam("index"))) {
	                            $count++;
	                        }
//	                        print_r("Set index van ".$child->getId().": ".$count);
	                        $child->setIndex($count);
		                    $child->setUserModification($this->getUser()->getId());
	                        $child->save();
	                        $count++;
	                    }
	                }
	
	                $document->setUserModification($this->getUser()->getId());
	                try {
//	                	print_r("save document<br/>");
	                    $document->save();
	                    $success = true;
	                } catch (Exception $e) {
	                    $this->_helper->json(array("success" => false, "message" => $e->getMessage()));
	                }
	            }
	            else {
	                Logger::debug("Prevented moving document, because document with same path+key already exists.");
	            }
	        } else if ($document->isAllowed("rename") &&  $this->_getParam("key_" . $language) ) {
	            //just rename
	            try {
	                    $document->setKey($this->_getParam("key_" . $language) );
		                $document->setUserModification($this->getUser()->getId());
	                    $document->save();
	                    $success = true;
	                } catch (Exception $e) {
	                    $this->_helper->json(array("success" => false, "message" => $e->getMessage()));
	                }
	        }
	        else {
	            Logger::debug("Prevented update document, because of missing permissions.");
	        }
	        
		}
        $this->_helper->json(array("success" => $success));
    }
	
}