<?php
/**
 * Plugin class of the SE I18N Plugin
 * 
 * @author Studio Emma
 * @version 10/02/2012
 * @package SEInternationalisation Plugin
 */
class SEInternationalisation_Plugin extends Pimcore_API_Plugin_Abstract implements Pimcore_API_Plugin_Interface {
	
	protected static $_savingDocuments = array();
	
	
    public static function needsReloadAfterInstall() {
        return true;
    }
    
    public function preDispatch() {
    	include('pimcore/modules/admin/controllers/DocumentController.php');			// te herzien
    }

	public static function install() {

		self::createTables();

		if (self::isInstalled()) {
			return "Plugin successfully installed.";
		} else {
			return "Plugin could not be installed";
		}
	}
    
    public static function createTables() {
		$result = true;

		// do the tables exist already?
		if(self::checkTables()) return true;

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

			// Create table
			$db->query("CREATE TABLE `se_i18n_keys` (
				  `document_id` bigint(20) NOT NULL,
				  `language` varchar(2) DEFAULT NULL,
				  `sourcePath` varchar(255) DEFAULT NULL,
				  PRIMARY KEY (`document_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");

			// Create folders for each Language
			$languages = (array)Pimcore_Tool::getValidLanguages();

			// Get list of sites
			$siteList = new Site_List();
			$sites = $siteList->load();

			if ($languages && count($languages) >= 1) {
				$primaryLanguage = $languages[0];
				foreach ($languages as $language) {
					$rootIds = array();

					if ($sites) {
						// Multisite website, add language folders for all subsites
						/**
						 * @var $site Site
						 */
						foreach ($sites as $site) {
							$rootIds[] = $site->getRootId();
						}
					} else {
						// No sites; just use the primary root
						$rootIds[] = 1;
					}

					foreach ($rootIds as $rootId) {
						$rootDocument = Document::getById($rootId);
						if ($rootDocument->getKey() ==  '') {
							$sourcePath = $rootDocument->getRealFullPath() . $primaryLanguage;
						} else {
							$sourcePath = $rootDocument->getRealFullPath() . '/' . $primaryLanguage;
						}

						if ($rootDocument->getKey() ==  '') {
							$rootPath = $rootDocument->getRealFullPath() . $language;
						} else {
							$rootPath = $rootDocument->getRealFullPath() . '/' . $language;
						}
						$folder = Document_Folder::getByPath($rootPath);
						if (!$folder) {
							$folder = Document_Folder::create($rootId, array(
								'key' => $language,
								"userOwner" => 1,
								"userModification" => 1,
								"published" => true,
							));
						}
						// Also set the language property for the document
						$folder->setProperty('language', 'text', $language);
						$folder->save();

						// Create enty in plugin table, so basic link is provided
						$sql = "INSERT INTO se_i18n_keys(document_id,language,sourcePath) VALUES(
					'" . $folder->getId() . "',
					'" . $language . "',
					'" . $sourcePath . "')";
						Pimcore_API_Plugin_Abstract::getDb()->query($sql);
					}
				}
			}
		} catch (Exception $e) {
			$result = false;
			Logger::error("Failed to create tables; ".$e->getMessage());
		}

		return $result;
    }

	public static function checkTables() {
		$result = true;

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

			$db->describeTable('se_i18n_keys');

		} catch (Zend_Db_Exception $e) {
			$result = false;
		}

		return $result;
	}

    public static function uninstall() {
		self::dropTables();

		if (!self::isInstalled()) {
			return "Plugin successfully uninstalled.";
		} else {
			return "Plugin could not be uninstalled";
		}
    }

	public static function dropTables() {
		$result = true;

		// don't the tables exist anymore?
		if(!self::checkTables()) return true;

		try {
			$db  = Pimcore_API_Plugin_Abstract::getDb();

			$db->query("DROP TABLE IF EXISTS `se_i18n_keys`");

		} catch (Zend_Db_Exception $e) {
			$result = false;
			Logger::error("Failed to remove tables; ".$e->getMessage());
		}

		return $result;
	}

	public static function isInstalled() {
		return self::checkTables();
	}
    
    public function postUpdateDocument(Document $document) {
	  	return;

    	$currentId = $document->getId();

    	$propertyFields = array('name', 'type', 'ctype', 'inheritable');

    	$languageDependentProperties = array("navigation_name", "navigation_title","navigation_target","navigation_exclude");

	    if (!in_array($currentId, self::$_savingDocuments)) {

		    self::$_savingDocuments[] = $currentId;

		    $languages = (array) Pimcore_Tool::getValidLanguages();

		    $sourceDocumentId = SEInternationalisation_Document::getDocumentIdInOtherLanguage($currentId, reset($languages));
    		$properties = $document->getProperties();
    		if ($sourceDocumentId != $currentId) {
	    		if (isset($properties['se_i18n_prop_chk_same_'.$currentId]) && isset($properties['se_i18n_prop_chk_same_'.$sourceDocumentId])) {
	    			$properties['se_i18n_prop_chk_same_'.$sourceDocumentId]->setData($properties['se_i18n_prop_chk_same_'.$currentId]->getData());
	    			unset($properties['se_i18n_prop_chk_same_'.$currentId]);
	    			$document->setProperties($properties);
	    			$document->save();

	    		} elseif (isset($properties['se_i18n_prop_chk_same_'.$currentId])) {
	    			$properties['se_i18n_prop_chk_same_'.$sourceDocumentId] = $properties['se_i18n_prop_chk_same_'.$currentId];
	    			$properties['se_i18n_prop_chk_same_'.$sourceDocumentId]->setName('se_i18n_prop_chk_same_'.$sourceDocumentId);
	    			unset($properties['se_i18n_prop_chk_same_'.$currentId]);
	    			$document->setProperties($properties);
	    			$document->save();

	    		}
    		}

    		$prop = $document->getProperty('se_i18n_prop_chk_same_'.$sourceDocumentId);

	    	if ($prop) {
	    		$properties = $document->getProperties();
	    		$docProps = array();
	    		foreach ($properties as $key => $property) {
	    			if ($property->getType() == "document" && !$property->getInherited()) {
	    				$docProps[$key] = $property;
	    			}
	    		}
	    		// text, checkbox, asset, object => ok
	    		// document => translate !!!


	    		foreach ($languages as $language) {
	    			$doc = null;
	    			$doc = Document::getById(SEInternationalisation_Document::getDocumentIdInOtherLanguage($currentId, $language));
	    			$document_id = $doc->getId();

	    			// Copy controller, action & template to all documents
	    			$doc->setAction($document->getAction());
	    			$doc->setController($document->getController());
	    			$doc->setTemplate($document->gettemplate());

	    			if ($document_id != $currentId) {
	    				if (!in_array($document_id, self::$_savingDocuments)) {
		    				self::$_savingDocuments[] = $document_id;
		    				foreach ($docProps as $propertyName => $originalProperty) {
		    					$property = new Property();
		    					foreach ($propertyFields as $field) {
		    						$property->{"set".ucfirst($field)}($originalProperty->{"get".ucfirst($field)}());
		    					}
		    					$data = $originalProperty->getData();
		    					if ($data) {
		    						$newDocument = Document::getById(SEInternationalisation_Document::getDocumentIdInOtherLanguage($data->getId(), $language));
		    						$property->setData($newDocument);
		    					}
		    				}

		    				// indien de property taalafhankelelijk is; kopieer de originele waarde in de nieuwe property = oude waarde behouden
		    				foreach ($properties as $key=>$property) {
		    					if (in_array($key, $languageDependentProperties)) {
		    						if ($doc->hasProperty($key)) {
		    							$properties[$key]->setData($doc->getProperty($key));
		    						}
		    					}
		    				}

		    				// Fix so multihref elements are not lost on save
		    				foreach($doc->getElements() as $key=>$el) {
		    					if ($el instanceof Document_Tag_Multihref) {
		    						$el->load();
		    					}
		    				}

		    				$doc->setProperties($properties);
			    			$doc->save();
			    			Pimcore_Model_Cache::clearTag("document_" . $doc->getId());
	    				}
	    			}
	    		}
	    	} else {
	    		foreach ($languages as $language) {
	    			$doc = Document::getById(SEInternationalisation_Document::getDocumentIdInOtherLanguage($currentId, $language));
	    			self::$_savingDocuments[] = $doc->getId();
	    			$properties = $doc->getProperties();
	    			foreach ($properties as $key => $value) {
	    				if (strpos($key, 'se_i18n_prop_chk_same_') !== false) {
	    					unset($properties[$key]);
	    				}
	    			}
	    			$doc->setProperties($properties);

	    			// Fix so multihref elements are not lost on save
	    			foreach($doc->getElements() as $key=>$el) {
	    				if ($el instanceof Document_Tag_Multihref) {
	    					$el->load();
	    				}
	    			}

	    			$doc->save();
	    			Pimcore_Model_Cache::clearTag("document_" . $doc->getId());
	    		}
	    	}
    	}
    }

    public static function getTranslationFile($language) {

    }

    public static function getInstallPath() {
        return PIMCORE_PLUGINS_PATH."/SEInternationalisation/install";
    }
}