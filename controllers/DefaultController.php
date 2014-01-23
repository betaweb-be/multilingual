<?php

class DefaultController extends Website_Controller_Action {
	
	/**
	 * this function
	 * @throws Exception
	 */
	public function languageDetectionAction () {
		// Get the browser language
		$locale = new Zend_Locale();
		$browserLanguage = $locale->getLanguage();
		
		$languages = (array) Pimcore_Tool::getValidLanguages();
		
		// Check if the browser language is a valid frontend language
		if (in_array($browserLanguage, $languages)) {
			$language = $browserLanguage;
		} else {
			// If it is not, take the first frontend language as default
			$language = $languages[0];
		}
		
		// UNTESTED
		// Get the folder of the current language
		$folder = Document_Folder::getByPath('/' . $language);
		if ($folder) {
			$document = SEInternationalisation_Document::findFirstDocumentByFolderId($folder->getId());
			if ($document) {
				$this->_redirect($document->getPath() . $document->getKey());
			} else {
				throw new Exception('No document found in your browser language');
			}
		} else {
			throw new Exception('No language folder found that matches your browser language');
		}
	}
	
	//
	// TODO: move this to the Website_Controller_Action
	public function getRelatedPages() {
		
		// Get current document
		
		// Get all frontend languages
		
		// Get related documents in all languages
		
		// Get related documents info (as Document)
		
		// Build language array with url,title params
	}
	

	
}