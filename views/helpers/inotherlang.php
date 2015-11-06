<?php
use Pimcore\Model\Document;

/**
 * Class Website_Helper_Inotherlang
 */
class Multilingual_Helper_Inotherlang extends Zend_View_Helper_Abstract
{

    /**
     * @param $document
     * @param null $language
     * @return Document|null
     */
    public function inotherlang($document, $language = null)
    {
        $documentInOtherLang = null;

        if (is_null($language)) {
            $language = CURRENT_LANGUAGE;
        }

        if ($document instanceof Document) {
            $id = $document->getId();
        } elseif (is_numeric($document)) {
            $id = $document;
        } else {
            $id = 0;
        }

        $otherLangId = null;
        try {
            if (class_exists('\\Multilingual\\Document')) {
                $otherLangId = \Multilingual\Document::getDocumentIdInOtherLanguage($id, $language);
            } else {
                $otherLangId = $id;
            }
        } catch (Exception $e) {

        }

        if ($otherLangId) {
            $documentInOtherLang = Document::getById($otherLangId);
        }

        return $documentInOtherLang;
    }
}
