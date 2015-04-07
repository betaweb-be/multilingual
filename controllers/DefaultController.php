<?php

use Pimcore\Controller\Action\Admin;
use Pimcore\Model\Document;
use Pimcore\Tool;

/**
 * Class Multilingual_DefaultController
 */
class Multilingual_DefaultController extends Admin
{
    /**
     * @throws Exception
     */
    public function goToFirstChildAction()
    {
        if ($this->document->hasChilds()) {
            $children = $this->document->getChilds();
            $firstChild = reset($children);
            $this->redirect($firstChild->getFullPath());
        } else {
            throw new Exception('No children found. Could not redirect to first child.');
        }
    }


    /**
     * @throws Exception
     */
    public function languageDetectionAction()
    {
        // Get the browser language
        $locale = new Zend_Locale();
        $browserLanguage = $locale->getLanguage();

        $languages = Tool::getValidLanguages();

        // Check if the browser language is a valid frontend language
        if (in_array($browserLanguage, $languages)) {
            $language = $browserLanguage;
        } else {
            // If it is not, take the first frontend language as default
            $language = reset($languages);
        }

        // Get the folder of the current language (in the current site)
        $currentSitePath = $this->document->getRealFullPath();
        $folder = Document\Page::getByPath($currentSitePath . '/' . $language);
        if ($folder) {
            $document = $this->findFirstDocumentByParentId($folder->getId());
            if ($document) {
                $this->redirect($document->getPath() . $document->getKey());
            } else {
                throw new Exception('No document found in your browser language');
            }
        } else {
            throw new Exception('No language folder found that matches your browser language');
        }
    }

    /**
     * Find the first active document for a given folder
     *
     * @param $folder_id
     * @return mixed
     */
    protected function findFirstDocumentByParentId($folder_id)
    {
        $list = new Document\Listing();
        $list->setCondition("parentId = ?", (int)$folder_id);
        $list->setOrderKey("index");
        $list->setOrder("asc");
        $list->setLimit(1);
        $childsList = $list->load();

        return reset($childsList);
    }
}