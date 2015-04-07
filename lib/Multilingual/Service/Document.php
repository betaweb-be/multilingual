<?php

namespace Multilingual\Service;

use Multilingual\Table\Keys;
use Pimcore\Config;
use Pimcore\Model\Document\Listing;
use Pimcore\Model\Document\Page;
use Pimcore\Model\Property;
use Pimcore\Tool;

class Document
{
    public function addLanguage($languageToAdd)
    {
        // Check if language is not already added
        if (in_array($languageToAdd, Tool::getValidLanguages())) {
            $result = false;
        } else {
            // Read all the documents from the first language
            $availableLanguages = Tool::getValidLanguages();
            $firstLanguageDocument = \Pimcore\Model\Document::getByPath('/' . reset($availableLanguages));

            \Zend_Registry::set('SEI18N_add', 1);

            // Add the language main folder
            $document = Page::create(
                1,
                array(
                    'key' => $languageToAdd,
                    "userOwner" => 1,
                    "userModification" => 1,
                    "published" => true,
                    "controller" => 'default',
                    "action" => 'go-to-first-child'
                )
            );
            $document->setProperty(
                'language',
                'text',
                $languageToAdd
            ); // Set the language to this folder and let it inherit to the child pages
            $document->setProperty(
                'isLanguageRoot',
                'text',
                1,
                false,
                false
            ); // Set as language root document
            $document->save();

            // Add Link to other languages
            $t = new Keys();
            $t->insert(
                array(
                    "document_id" => $document->getId(),
                    "language" => $languageToAdd,
                    "sourcePath" => $firstLanguageDocument->getFullPath(),
                )
            );

            // Lets add all the docs
            $this->addDocuments($firstLanguageDocument->getId(), $languageToAdd);

            \Zend_Registry::set('SEI18N_add', 0);

            $oldConfig = Config::getSystemConfig();
            $settings = $oldConfig->toArray();

            $languages = explode(',', $settings['general']['validLanguages']);
            $languages[] = $languageToAdd;

            $settings['general']['validLanguages'] = implode(',', $languages);

            $config = new \Zend_Config($settings, true);
            $writer = new \Zend_Config_Writer_Xml(
                array(
                    "config" => $config,
                    "filename" => PIMCORE_CONFIGURATION_SYSTEM
                )
            );
            $writer->write();

            $result = true;

        }

        return $result;
    }

    /**
     * @param $parentId
     * @param $languageToAdd
     */
    protected function addDocuments($parentId, $languageToAdd)
    {
        $list = new Listing();
        $list->setCondition('parentId = ?', $parentId);
        $list = $list->load();

        foreach ($list as $document) {
            // Create new document

            $targetParent = \Pimcore\Model\Document::getById(
                \Multilingual\Document::getDocumentIdInOtherLanguage($document->getParentId(), $languageToAdd)
            );
            /** @var Document_Page $target */
            $target = clone $document;
            $target->id = null;
            $target->setParent($targetParent);

            // Only sync properties when it is allowed
            if (!$target->hasProperty('doNotSyncProperties') && !$document->hasProperty(
                    'doNotSyncProperties'
                )
            ) {
                $editableDocumentTypes = array('page', 'email', 'snippet');
                if (in_array($document->getType(), $editableDocumentTypes)) {
                    $target->setContentMasterDocument($document);
                }

                // Set the properties the same
                $sourceProperties = $document->getProperties();
                /** @var string $key
                 * @var Property $value
                 */
                foreach ($sourceProperties as $key => $value) {
                    if (!$target->hasProperty($key)) {
                        $propertyValue = $value->getData();
                        if ($value->getType() == 'document') {
                            $propertyValue = \Multilingual\Document::getDocumentIdInOtherLanguage(
                                $value->getData()->getId(),
                                $languageToAdd
                            );
                        }
                        $target->setProperty(
                            $key,
                            $value->getType(),
                            $propertyValue,
                            false,
                            $value->getInheritable()
                        );
                    }
                }
            }

            $target->save();


            // Add Link to other languages
            $t = new Keys();
            $t->insert(
                array(
                    "document_id" => $target->getId(),
                    "language" => $languageToAdd,
                    "sourcePath" => $document->getFullPath(),
                )
            );

            // Check for children
            if (count($document->getChilds()) >= 1) {
                // Add the kids
                $this->addDocuments($document->getId(), $languageToAdd);
            }
        }
    }
}