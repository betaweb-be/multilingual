<?php

use Pimcore\Model\Document;
use Pimcore\Tool;

require_once "pimcore/modules/admin/controllers/DocumentController.php";

class Multilingual_IndexController extends Admin_DocumentController
{

    /**
     * Original function
     * @see Admin_DocumentController::treeGetChildsByIdAction()
     */
    public function treeGetChildsByIdAction()
    {
        $languages = Tool::getValidLanguages();
        $language = $this->_getParam("language", reset($languages));


        $document = Document::getById($this->getParam("node"));

        $documents = array();
        if ($document->hasChilds()) {
            $limit = intval($this->getParam("limit"));
            if (!$this->getParam("limit")) {
                $limit = 100000000;
            }
            $offset = intval($this->getParam("start"));

            $list = new Document\Listing();
            if ($this->getUser()->isAdmin()) {
                $list->setCondition("parentId = ? ", $document->getId());
            } else {

                $userIds = $this->getUser()->getRoles();
                $userIds[] = $this->getUser()->getId();
                $list->setCondition("parentId = ? and
                                        (
                                        (select list from users_workspaces_document where userId in (" . implode(',',
                        $userIds) . ") and LOCATE(CONCAT(path,`key`),cpath)=1  ORDER BY LENGTH(cpath) DESC LIMIT 1)=1
                                        or
                                        (select list from users_workspaces_document where userId in (" . implode(',',
                        $userIds) . ") and LOCATE(cpath,CONCAT(path,`key`))=1  ORDER BY LENGTH(cpath) DESC LIMIT 1)=1
                                        )", $document->getId());
            }
            $list->setOrderKey("index");
            $list->setOrder("asc");
            $list->setLimit($limit);
            $list->setOffset($offset);

            $childsList = $list->load();

            foreach ($childsList as $childDocument) {
                // only display document if listing is allowed for the current user
                if ($childDocument->isAllowed("list")) {
                    if ($childDocument instanceof Document\Page &&
                        $childDocument->hasProperty('isLanguageRoot') &&
                        $childDocument->getProperty('isLanguageRoot') == 1
                    ) {
                        if ($childDocument->getKey() == $language) {
//                            $documents[] = $this->getTreeNodeConfig($childDocument);
                            $config = $this->getTreeNodeConfig($childDocument);
                            $config['expanded'] = true;
                            $documents[] = $config;
                        }
                    } else {
                        $documents[] = $this->getTreeNodeConfig($childDocument);
                    }
                }
            }
        }

        if ($this->getParam("limit")) {
            $this->_helper->json(array(
                "offset" => $offset,
                "limit" => $limit,
                "total" => $document->getChildAmount($this->getUser()),
                "nodes" => $documents
            ));
        } else {
            $this->_helper->json($documents);
        }

        $this->_helper->json(false);
    }
}