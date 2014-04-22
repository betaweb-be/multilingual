<?php

/**
 * Class SEInternationalisation_Document
 */
class SEInternationalisation_Document
{


	/**
	 * @param $document_id
	 * @param $language
	 * @return Document
	 * @throws Exception
	 * @throws Zend_Db_Table_Exception
	 */
	public static function getDocumentIdInOtherLanguage($document_id, $language)
	{
		$tSource = new SEInternationalisation_Table_Keys();
		$tTarget = new SEInternationalisation_Table_Keys();
		$select = $tSource->select()
			->from(array("s" => $tSource->info("name")), array())
			->from(array("t" => $tTarget->info("name")), array("document_id"))
			->where("s.document_id = ?", $document_id)
			->where("s.sourcePath = t.sourcePath")
			->where("t.language = ?", $language);
		$row = $tSource->fetchRow($select);
		if (!empty($row)) {
			return $row->document_id;
		} else {
			throw new Exception("Document (" . $document_id . ") not available in " . $language);
		}
	}

}