<?php

/**
 * Plugin class of the SE I18N Plugin
 *
 * @author Studio Emma
 * @package SEInternationalisation Plugin
 */
class SEInternationalisation_Plugin extends Pimcore_API_Plugin_Abstract implements Pimcore_API_Plugin_Interface
{

	/**
	 * @throws Zend_EventManager_Exception_InvalidArgumentException
	 */
	public function init()
	{
		Pimcore::getEventManager()->attach("document.postAdd", array($this, "createDocument"));
		Pimcore::getEventManager()->attach("document.postDelete", array($this, "deleteDocument"));
		Pimcore::getEventManager()->attach("document.postUpdate", array($this, "updateDocument"));
	}

	/**
	 * Event that handles the creation of a document
	 *
	 * @param $e
	 * @throws Exception
	 * @throws Zend_Exception
	 */
	public function createDocument($e)
	{

		// Check if this function is not in progress
		if (Zend_Registry::isRegistered('SEI18N_add') && Zend_Registry::get('SEI18N_add') == 1) {
			return;
		}
		// Lock this event-trigger
		Zend_Registry::set('SEI18N_add', 1);

		/**@var Document $doc */
		$doc = $e->getTarget();


		// Get current language
		$sourceLanguage = $doc->getProperty('language');
		$sourceParent = $doc->getParent();

		$sourceHash = $doc->getFullPath();

		// Add Link to other languages
		$t = new SEInternationalisation_Table_Keys();
		$t->insert(
			array(
				"document_id" => $doc->getId(),
				"language" => $sourceLanguage,
				"sourcePath" => $sourceHash,
			)
		);

		// Create folders for each Language
		$languages = (array)Pimcore_Tool::getValidLanguages();
		foreach ($languages as $language) {
			if ($language != $sourceLanguage) {
				$targetParent = SEInternationalisation_Document::getDocumentInOtherLanguage($sourceParent, $language);
				if ($targetParent) {
					/** @var Document_Page $target */
					$target = clone $doc;

					// Reset ID (so it will create a new document)
					$target->id = null;

					// Set new parent
					$target->setParent($targetParent);

					// Check if we can link a master document
					$editableDocumentTypes = array('page', 'email', 'snippet');
					if (in_array($doc->getType(), $editableDocumentTypes)) {
						$target->setContentMasterDocument($doc);
					}

					// Save the new document
					$target->save();

					// Add Link to other languages
					$t = new SEInternationalisation_Table_Keys();
					$t->insert(
						array(
							"document_id" => $target->getId(),
							"language" => $language,
							"sourcePath" => $sourceHash,
						)
					);
				}
			}
		}

		// Unlock this event-trigger
		Zend_Registry::set('SEI18N_add', 0);
	}

	/**
	 * Event after deleting a document
	 *
	 * Deletes all related documents
	 *
	 * @param $e
	 * @throws Exception
	 * @throws Zend_Exception
	 */
	public function deleteDocument($e)
	{
		// Check if this function is not in progress
		if (Zend_Registry::isRegistered('SEI18N_delete') && Zend_Registry::get('SEI18N_delete') == 1) {
			return;
		}
		// Lock this event-trigger
		Zend_Registry::set('SEI18N_delete', 1);

		/**@var Document $sourceDocument */
		$sourceDocument = $e->getTarget();

		// Get current language
		$sourceLanguage = $sourceDocument->getProperty('language');

		// Remove document in each language
		$languages = (array)Pimcore_Tool::getValidLanguages();
		foreach ($languages as $language) {
			if ($language != $sourceLanguage) {
				$target = SEInternationalisation_Document::getDocumentInOtherLanguage($sourceDocument, $language);
				if ($target) {
					$target->delete();
				}
			}
		}

		// Remove link to other documents
		$t = new SEInternationalisation_Table_Keys();
		$row = $t->fetchRow('document_id = ' . $sourceDocument->getId());
		$t->delete('sourcePath = "' . $row->sourcePath . '"');

		// Unlock this event-trigger
		Zend_Registry::set('SEI18N_delete', 0);
	}

	/**
	 * Event that handles the update of a Document
	 *
	 * @param $e
	 * @throws Exception
	 * @throws Zend_Exception
	 */
	public function updateDocument($e)
	{
		// Check if this function is not in progress
		if (Zend_Registry::isRegistered('SEI18N_' . __FUNCTION__) && Zend_Registry::get(
				'SEI18N_' . __FUNCTION__
			) == 1
		) {
			return;
		}

		// Lock this event-trigger
		Zend_Registry::set('SEI18N_' . __FUNCTION__, 1);

		/**@var Document_Page $sourceDocument */
		$sourceDocument = $e->getTarget();

		// Get current language
		$sourceLanguage = $sourceDocument->getProperty('language');

		// Get the Source Parent (we have to do it this way, due to a bug in Pimcore)
		$sourceParent = Document::getById($sourceDocument->getParentId());

		// Update SourcePath in SEI18N table
		$t = new SEInternationalisation_Table_Keys();
		$row = $t->fetchRow('document_id = ' . $sourceDocument->getId());
		$t->update(array('sourcePath' => $sourceDocument->getFullPath()), 'sourcePath = "' . $row->sourcePath . '"');

		// Update each language
		$languages = (array)Pimcore_Tool::getValidLanguages();
		foreach ($languages as $language) {
			if ($language != $sourceLanguage) {

				// Find the target document
				/** @var Document $targetDocument */
				$targetDocument = SEInternationalisation_Document::getDocumentInOtherLanguage(
					$sourceDocument,
					$language
				);
				if ($targetDocument) {
					// Find the parent
					$targetParent = SEInternationalisation_Document::getDocumentInOtherLanguage(
						$sourceParent,
						$language
					);

					// Only sync properties when it is allowed
					if (!$targetDocument->hasProperty('doNotSyncProperties') && !$sourceDocument->hasProperty(
							'doNotSyncProperties'
						)
					) {
						$typeHasChanged = false;

						// Set document type (for conversion)
						if ($targetDocument->getType() != $sourceDocument->getType()) {
							$typeHasChanged = true;
							$targetDocument->setType($sourceDocument->getType());

							if ($targetDocument->getType() == "hardlink" || $targetDocument->getType() == "folder") {
								// remove navigation settings
								foreach ([
											 "name",
											 "title",
											 "target",
											 "exclude",
											 "class",
											 "anchor",
											 "parameters",
											 "relation",
											 "accesskey",
											 "tabindex"
										 ] as $propertyName) {
									$targetDocument->removeProperty("navigation_" . $propertyName);
								}
							}

							// overwrite internal store to avoid "duplicate full path" error
							Zend_Registry::set("document_" . $targetDocument->getId(), $targetDocument);
						}

						// Set the controller the same
						$editableDocumentTypes = array('page', 'email', 'snippet');
						if (!$typeHasChanged && in_array($sourceDocument->getType(), $editableDocumentTypes)) {
							/** @var Document_Page $targetDocument */
							$targetDocument->setController($sourceDocument->getController());
							$targetDocument->setAction($sourceDocument->getAction());
						}

						// Set the properties the same
						// But only if they have not been added already
						$sourceProperties = $sourceDocument->getProperties();
						/** @var string $key
						 * @var Property $value
						 */
						foreach ($sourceProperties as $key => $value) {
							if (strpos($key, 'navigation_') === false) {
								if (!$targetDocument->hasProperty($key)) {
									$propertyValue = $value->getData();
									if ($value->getType() == 'document') {
										$propertyValue = SEInternationalisation_Document::getDocumentIdInOtherLanguage(
											$value->getData()->getId(),
											$language
										);
									}
									$targetDocument->setProperty(
										$key,
										$value->getType(),
										$propertyValue,
										false,
										$value->getInheritable()
									);
								}
							}
						}


					}

					// Make sure the parent stays the same
					$targetDocument->setParent($targetParent);
					$targetDocument->setParentId($targetParent->getId());
					$targetDocument->setPath($targetParent->getFullPath() . '/');

					// Make sure the index stays the same
					$targetDocument->setIndex($sourceDocument->getIndex());

					// Make sure the index follows in all the pages at current level
					$list = new Document_List();
					$list->setCondition(
						"parentId = ? AND id != ?",
						array($targetParent->getId(), $sourceDocument->getId())
					);
					$list->setOrderKey("index");
					$list->setOrder("asc");
					$childsList = $list->load();

					$count = 0;
					/** @var Document $child */
					foreach ($childsList as $child) {
						if ($count == intval($targetDocument->getIndex())) {
							$count++;
						}
						$child->saveIndex($count);
						$count++;
					}

					$targetDocument->save();
				}
			}
		}

		// Unlock this event-trigger
		Zend_Registry::set('SEI18N_' . __FUNCTION__, 0);
	}

	/**
	 * @return bool
	 */
	public static function needsReloadAfterInstall()
	{
		return true;
	}


	/**
	 * @return string
	 */
	public static function install()
	{

		self::createTables();

		if (self::isInstalled()) {
			return "Plugin successfully installed.";
		} else {
			return "Plugin could not be installed";
		}
	}

	/**
	 * @return bool
	 */
	public static function createTables()
	{
		$result = true;

		// do the tables exist already?
		if (self::checkTables()) {
			return true;
		}

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

			// Create table
			$db->query(
				"CREATE TABLE `se_i18n_keys` (
								  `document_id` bigint(20) NOT NULL,
								  `language` varchar(2) DEFAULT NULL,
								  `sourcePath` varchar(255) DEFAULT NULL,
								  PRIMARY KEY (`document_id`)
								) ENGINE=MyISAM DEFAULT CHARSET=utf8;
							"
			);

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
						if ($rootDocument->getKey() == '') {
							$sourcePath = $rootDocument->getRealFullPath() . $primaryLanguage;
						} else {
							$sourcePath = $rootDocument->getRealFullPath() . '/' . $primaryLanguage;
						}

						if ($rootDocument->getKey() == '') {
							$rootPath = $rootDocument->getRealFullPath() . $language;
						} else {
							$rootPath = $rootDocument->getRealFullPath() . '/' . $language;
						}
						$folder = Document_Page::getByPath($rootPath);
						if (!$folder) {
							$folder = Document_Page::create(
								$rootId,
								array(
									'key' => $language,
									"userOwner" => 1,
									"userModification" => 1,
									"published" => true,
									"controller" => 'default',
									"action" => 'go-to-first-child'
								)
							);
						}
						// Also set the language property for the document
						$folder->setProperty('language', 'text', $language);
						$folder->setProperty('isLanguageRoot', 'text', 1, false, false);
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
			Logger::error("Failed to create tables; " . $e->getMessage());
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	public static function checkTables()
	{
		$result = true;

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

			$db->describeTable('se_i18n_keys');

		} catch (Zend_Db_Exception $e) {
			$result = false;
		}

		return $result;
	}

	/**
	 * @return string
	 */
	public static function uninstall()
	{
		self::dropTables();

		if (!self::isInstalled()) {
			return "Plugin successfully uninstalled.";
		} else {
			return "Plugin could not be uninstalled";
		}
	}

	/**
	 * @return bool
	 */
	public static function dropTables()
	{
		$result = true;

		// don't the tables exist anymore?
		if (!self::checkTables()) {
			return true;
		}

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

			$db->query("DROP TABLE IF EXISTS `se_i18n_keys`");

		} catch (Zend_Db_Exception $e) {
			$result = false;
			Logger::error("Failed to remove tables; " . $e->getMessage());
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	public static function isInstalled()
	{
		return self::checkTables();
	}

}