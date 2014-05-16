<?php

/**
 * Class SEInternationalisation_Document
 */
class SEInternationalisation_Document
{


	/**
	 * @param $document_id
	 * @param $language
	 * @return int
	 * @throws Exception
	 * @throws Zend_Db_Table_Exception
	 */
	public static function getDocumentIdInOtherLanguage($document_id, $language)
	{
		// check if this in the cache
		$cacheService = Website_Service_Cache::getInstance();
		$cacheData = $cacheService->load('SEI18N');

		if (isset($cacheData[$document_id][$language])) {
			return $cacheData[$document_id][$language];
		}

		$tSource = new SEInternationalisation_Table_Keys();
		$tTarget = new SEInternationalisation_Table_Keys();
		$select = $tSource->select()
			->from(array("s" =>$tSource->info("name")), array())
			->from(array("t" => $tTarget->info("name")), array("document_id"))
			->where("s.document_id = ?", $document_id)
			->where("s.sourcePath = t.sourcePath")
			->where("t.language = ?", $language);
		$row = $tSource->fetchRow($select);
		if (!empty($row)) {
			$cacheData[$document_id][$language] = $row->document_id;
			$cacheService->write("SEI18N",$cacheData,'month','SEI18N');
			return $row->document_id;
		} else {
			throw new Exception("Document (".$document_id.") not available in ".$language);
		}
	}

	/**
	 * @param $sourceDocument
	 * @param $intendedLanguage
	 * @return Document
	 * @throws Exception
	 * @throws Zend_Db_Table_Exception
	 */
	public static function getDocumentInOtherLanguage($sourceDocument,$intendedLanguage) {
		if ($sourceDocument instanceof Document) {
			$documentId = $sourceDocument->getId();
		} else {
			$documentId = $sourceDocument;
		}

		// check if this in the cache
		$cacheService = Website_Service_Cache::getInstance();
		$cacheData = $cacheService->load('SEI18N');

		if (isset($cacheData[$documentId][$intendedLanguage])) {
			return Document::getById($cacheData[$documentId][$intendedLanguage]);
		}

		$tSource = new SEInternationalisation_Table_Keys();
		$tTarget = new SEInternationalisation_Table_Keys();
		$select = $tSource->select()
			->from(array("s" =>$tSource->info("name")), array())
			->from(array("t" => $tTarget->info("name")), array("document_id"))
			->where("s.document_id = ?", $documentId)
			->where("s.sourcePath = t.sourcePath")
			->where("t.language = ?", $intendedLanguage);
		$row = $tSource->fetchRow($select);
		if (!empty($row)) {
			$cacheData[$documentId][$intendedLanguage] = $row->document_id;
			$cacheService->write("SEI18N",$cacheData,'month','SEI18N');
			return Document::getById($row->document_id);
		} else {
			error_log("Document (".$documentId.") not available in ".$intendedLanguage);
			return false;
		}

	}

}