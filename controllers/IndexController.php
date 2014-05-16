<?php

require_once "pimcore/modules/admin/controllers/DocumentController.php";

class SEInternationalisation_IndexController extends Admin_DocumentController
{

	/**
	 * Original function
	 * @see Admin_DocumentController::treeGetChildsByIdAction()
	 */
	public function treeGetChildsByIdAction()
	{
		$languages = (array)Pimcore_Tool::getValidLanguages();
		$language = $this->_getParam("language", reset($languages));
		$node = $this->_getParam("node");
		$document = Document::getById($node);

		$documents = array();
		if ($document->hasChilds()) {
			$limit = intval($this->_getParam("limit"));
			if (!$this->_getParam("limit")) {
				$limit = 100000000;
			}
			$offset = intval($this->_getParam("start"));

			$list = new Document_List();
			$list->setCondition("parentId = ?", (int)$document->getId());
			$list->setOrderKey("index");
			$list->setOrder("asc");
			$list->setLimit($limit);
			$list->setOffset($offset);
			$childsList = $list->load();

			foreach ($childsList as $childDocument) {
				if ($childDocument->isAllowed("list")) {
					$config = $this->getTreeNodeConfig($childDocument);
					if ($node == 1) {
						$config["expanded"] = true;
					}

					if ($childDocument instanceof Document_Page && strlen($childDocument->getKey()) == 2) {
						if ($childDocument->getKey() == $language) {
							$documents[] = $config;
						}
					} else {
						$documents[] = $config;
					}
				}

			}

		}

		if ($this->_getParam("limit")) {
			$this->_helper->json(
				array(
					"total" => count($documents),
					"nodes" => $documents
				)
			);
		} else {
			$this->_helper->json($documents);
		}

		$this->_helper->json(false);
	}
}