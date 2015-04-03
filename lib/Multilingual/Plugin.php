<?php


namespace Multilingual;

use Exception;
use Logger;
use Multilingual\Table\Keys;
use Pimcore\API\Plugin as PluginLib;
use Pimcore\Model\Document;
use Pimcore\Model\Property;
use Pimcore\Model\Site;
use Pimcore\Tool;

/**
 * Class Plugin
 * @package Multilingual
 */
class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{
    /**
     * @var string
     */
    protected static $_tableName = 'plugin_multilingual_keys';

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
        if (self::checkTables()) {
            return true;
        }

        try {
            $db = PluginLib\AbstractPlugin::getDb();

            // Create table
            $db->query(
                "CREATE TABLE `" . self::$_tableName . "` (
								  `document_id` BIGINT(20) NOT NULL,
								  `language` VARCHAR(2) DEFAULT NULL,
								  `sourcePath` VARCHAR(255) DEFAULT NULL,
								  PRIMARY KEY (`document_id`)
								) ENGINE=MyISAM DEFAULT CHARSET=utf8;
							"
            );

            $languages = Tool::getValidLanguages();

            // Get list of sites
            $siteList = new \Pimcore\Model\Site\Listing();
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
                        $folder = Document\Page::getByPath($rootPath);
                        if (!$folder) {
                            $folder = Document\Page::create(
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
                        $sql = "INSERT INTO " . self::$_tableName . "(document_id,LANGUAGE,sourcePath) VALUES(
					'" . $folder->getId() . "',
					'" . $language . "',
					'" . $sourcePath . "')";
                        $db->query($sql);
                    }
                }
            }
        } catch (Exception $e) {
            $result = false;
            Logger::error("Failed to create tables: " . $e->getMessage());
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
            $db = PluginLib\AbstractPlugin::getDb();
            $db->describeTable(self::$_tableName);
        } catch (Exception $e) {
            $result = false;
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

        if (!self::checkTables()) {
            return true;
        }

        try {
            $db = PluginLib\AbstractPlugin::getDb();
            $db->query('DROP TABLE IF EXISTS ' . self::$_tableName);

        } catch (Exception $e) {
            $result = false;
            Logger::error("Failed to remove tables; " . $e->getMessage());
        }

        return $result;
    }

    /**
     * @throws \Zend_EventManager_Exception_InvalidArgumentException
     */
    public function init()
    {
        \Pimcore::getEventManager()->attach("document.postAdd", array($this, "createDocument"));
        \Pimcore::getEventManager()->attach("document.postDelete", array($this, "deleteDocument"));
        \Pimcore::getEventManager()->attach("document.postUpdate", array($this, "updateDocument"));
        \Pimcore::getEventManager()->attach("admin.object.treeGetChildsById.preSendData", function($e) {
            echo "<pre>";
            print_r($e);
            exit;
        });
    }

    /**
     * @param $e \Zend_EventManager_Event
     * @throws Exception
     * @throws \Zend_Exception
     */
    public function createDocument($e)
    {

        // Check if this function is not in progress
        if (\Zend_Registry::isRegistered('Multilingual_add') && \Zend_Registry::get('Multilingual_add') == 1) {
            return;
        }
        // Lock this event-trigger
        \Zend_Registry::set('Multilingual_add', 1);

        /**@var Document $doc */
        $doc = $e->getTarget();


        // Get current language
        $sourceLanguage = $doc->getProperty('language');
        $sourceParent = $doc->getParent();

        $sourceHash = $doc->getFullPath();

        // Add Link to other languages
        $t = new Keys();
        $t->insert(
            array(
                "document_id" => $doc->getId(),
                "language" => $sourceLanguage,
                "sourcePath" => $sourceHash,
            )
        );

        // Create folders for each Language
        $languages = Tool::getValidLanguages();
        foreach ($languages as $language) {
            if ($language != $sourceLanguage) {
                $targetParent = \Multilingual\Document::getDocumentInOtherLanguage($sourceParent, $language);
                if ($targetParent) {
                    /** @var Document\Page $target */
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
                    $t = new Keys();
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
        \Zend_Registry::set('Multilingual_add', 0);
    }

    /**
     * Event after deleting a document
     *
     * Deletes all related documents
     *
     * @param $e \Zend_EventManager_Event
     * @throws Exception
     * @throws \Zend_Exception
     */
    public function deleteDocument($e)
    {
        // Check if this function is not in progress
        if (\Zend_Registry::isRegistered('Multilingual_delete') && \Zend_Registry::get('Multilingual_delete') == 1) {
            return;
        }
        // Lock this event-trigger
        \Zend_Registry::set('Multilingual_delete', 1);

        /**@var Document $sourceDocument */
        $sourceDocument = $e->getTarget();

        // Get current language
        $sourceLanguage = $sourceDocument->getProperty('language');

        // Remove document in each language
        $languages = Tool::getValidLanguages();
        foreach ($languages as $language) {
            if ($language != $sourceLanguage) {
                $target = \Multilingual\Document::getDocumentInOtherLanguage($sourceDocument, $language);
                if ($target) {
                    $target->delete();
                }
            }
        }

        // Remove link to other documents
        $t = new Keys();
        $row = $t->fetchRow('document_id = ' . $sourceDocument->getId());
        $t->delete('sourcePath = "' . $row->sourcePath . '"');

        // Unlock this event-trigger
        \Zend_Registry::set('Multilingual_delete', 0);
    }

    /**
     * Event that handles the update of a Document
     *
     * @param $e \Zend_EventManager_Event
     * @throws Exception
     * @throws \Zend_Exception
     */
    public function updateDocument($e)
    {
        // Check if this function is not in progress
        if (\Zend_Registry::isRegistered('Multilingual_' . __FUNCTION__) && \Zend_Registry::get(
                'Multilingual_' . __FUNCTION__
            ) == 1
        ) {
            return;
        }

        // Lock this event-trigger
        \Zend_Registry::set('Multilingual_' . __FUNCTION__, 1);

        /**@var Document\Page $sourceDocument */
        $sourceDocument = $e->getTarget();

        // Get current language
        $sourceLanguage = $sourceDocument->getProperty('language');

        // Get the Source Parent (we have to do it this way, due to a bug in Pimcore)
        $sourceParent = Document::getById($sourceDocument->getParentId());

        // Update SourcePath in Multilingual table
        $t = new Keys();
        $row = $t->fetchRow('document_id = ' . $sourceDocument->getId());
        $t->update(array('sourcePath' => $sourceDocument->getFullPath()), 'sourcePath = "' . $row->sourcePath . '"');

        // Update each language
        $languages = Tool::getValidLanguages();
        foreach ($languages as $language) {
            if ($language != $sourceLanguage) {

                // Find the target document
                /** @var Document $targetDocument */
                $targetDocument = \Multilingual\Document::getDocumentInOtherLanguage(
                    $sourceDocument,
                    $language
                );
                if ($targetDocument) {
                    // Find the parent
                    $targetParent = \Multilingual\Document::getDocumentInOtherLanguage(
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
                            \Zend_Registry::set("document_" . $targetDocument->getId(), $targetDocument);
                        }

                        // Set the controller the same
                        $editableDocumentTypes = array('page', 'email', 'snippet');
                        if (!$typeHasChanged && in_array($sourceDocument->getType(), $editableDocumentTypes)) {
                            /** @var Document\Page $targetDocument */
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
                                        $propertyValue = \Multilingual\Document::getDocumentIdInOtherLanguage(
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
                    $list = new Document\Listing();
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
        \Zend_Registry::set('Multilingual_' . __FUNCTION__, 0);
    }
}
