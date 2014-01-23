<?php
class SEInternationalisation_Document {
	
	
	public static function getDocumentIdInOtherLanguage($document_id, $language) {
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
			return $row->document_id;
		} else {
			die(print_r(debug_backtrace()));
			throw new Exception("Document (".$document_id.") not available in ".$language);
		}
	}
	
}