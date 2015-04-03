<?php

namespace Multilingual;

use Multilingual\Table\Keys;

/**
 * Class Multilingual_Document
 */
class Document
{

    /**
     * @param $documentId
     * @param $language
     * @return bool|string
     */
    public static function getDocumentIdInOtherLanguage($documentId, $language)
    {
        $tSource = new Keys();
        $tTarget = new Keys();
        $select = $tSource->select()
            ->from(array("s" => $tSource->info("name")), array())
            ->from(array("t" => $tTarget->info("name")), array("document_id"))
            ->where("s.document_id = ?", $documentId)
            ->where("s.sourcePath = t.sourcePath")
            ->where("t.language = ?", $language);
        $row = $tSource->fetchRow($select);
        if (!empty($row)) {
            return $row->document_id;
        } else {
            return false;
        }
    }

    /**
     * @param $sourceDocument
     * @param $intendedLanguage
     * @return bool|\Pimcore\Model\Document
     */
    public static function getDocumentInOtherLanguage($sourceDocument, $intendedLanguage)
    {
        if ($sourceDocument instanceof \Pimcore\Model\Document) {
            $documentId = $sourceDocument->getId();
        } else {
            $documentId = $sourceDocument;
        }

        $tSource = new Keys();
        $tTarget = new Keys();
        $select = $tSource->select()
            ->from(array("s" => $tSource->info("name")), array())
            ->from(array("t" => $tTarget->info("name")), array("document_id"))
            ->where("s.document_id = ?", $documentId)
            ->where("s.sourcePath = t.sourcePath")
            ->where("t.language = ?", $intendedLanguage);
        $row = $tSource->fetchRow($select);
        if (!empty($row)) {
            return \Pimcore\Model\Document::getById($row->document_id);
        } else {
            return false;
        }
    }

}