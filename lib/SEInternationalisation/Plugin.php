<?php

/**
 * Plugin class of the SE I18N Plugin
 *
 * @author Studio Emma
 * @package SEInternationalisation Plugin
 */
class SEInternationalisation_Plugin extends Pimcore_API_Plugin_Abstract implements Pimcore_API_Plugin_Interface
{
	/**
	 * @return bool
	 */
	public static function needsReloadAfterInstall()
	{
		return true;
	}

	/**
	 *
	 */
	public function preDispatch()
	{
		include('pimcore/modules/admin/controllers/DocumentController.php'); // te herzien
	}

	/**
	 * @return string
	 */
	public static function install()
	{

		self::createTables();

		if (self::isInstalled()) {
			return "Plugin successfully installed.";
		} else {
			return "Plugin could not be installed";
		}
	}

	/**
	 * @return bool
	 */
	public static function createTables()
	{
		$result = true;

		// do the tables exist already?
		if (self::checkTables()) {
			return true;
		}

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

			// Create table
			$db->query(
				"CREATE TABLE `se_i18n_keys` (
								  `document_id` bigint(20) NOT NULL,
								  `language` varchar(2) DEFAULT NULL,
								  `sourcePath` varchar(255) DEFAULT NULL,
								  PRIMARY KEY (`document_id`)
								) ENGINE=MyISAM DEFAULT CHARSET=utf8;
							"
			);

			// Create folders for each Language
			$languages = (array)Pimcore_Tool::getValidLanguages();

			// Get list of sites
			$siteList = new Site_List();
			$sites = $siteList->load();

			if ($languages && count($languages) >= 1) {
				$primaryLanguage = $languages[0];
				foreach ($languages as $language) {
					$rootIds = array();

					if ($sites) {
						// Multisite website, add language folders for all subsites
						/**
						 * @var $site Site
						 */
						foreach ($sites as $site) {
							$rootIds[] = $site->getRootId();
						}
					} else {
						// No sites; just use the primary root
						$rootIds[] = 1;
					}

					foreach ($rootIds as $rootId) {
						$rootDocument = Document::getById($rootId);
						if ($rootDocument->getKey() == '') {
							$sourcePath = $rootDocument->getRealFullPath() . $primaryLanguage;
						} else {
							$sourcePath = $rootDocument->getRealFullPath() . '/' . $primaryLanguage;
						}

						if ($rootDocument->getKey() == '') {
							$rootPath = $rootDocument->getRealFullPath() . $language;
						} else {
							$rootPath = $rootDocument->getRealFullPath() . '/' . $language;
						}
						$folder = Document_Folder::getByPath($rootPath);
						if (!$folder) {
							$folder = Document_Folder::create(
								$rootId,
								array(
									'key' => $language,
									"userOwner" => 1,
									"userModification" => 1,
									"published" => true,
								)
							);
						}
						// Also set the language property for the document
						$folder->setProperty('language', 'text', $language);
						$folder->save();

						// Create enty in plugin table, so basic link is provided
						$sql = "INSERT INTO se_i18n_keys(document_id,language,sourcePath) VALUES(
					'" . $folder->getId() . "',
					'" . $language . "',
					'" . $sourcePath . "')";
						Pimcore_API_Plugin_Abstract::getDb()->query($sql);
					}
				}
			}
		} catch (Exception $e) {
			$result = false;
			Logger::error("Failed to create tables; " . $e->getMessage());
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	public static function checkTables()
	{
		$result = true;

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

			$db->describeTable('se_i18n_keys');

		} catch (Zend_Db_Exception $e) {
			$result = false;
		}

		return $result;
	}

	/**
	 * @return string
	 */
	public static function uninstall()
	{
		self::dropTables();

		if (!self::isInstalled()) {
			return "Plugin successfully uninstalled.";
		} else {
			return "Plugin could not be uninstalled";
		}
	}

	/**
	 * @return bool
	 */
	public static function dropTables()
	{
		$result = true;

		// don't the tables exist anymore?
		if (!self::checkTables()) {
			return true;
		}

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

			$db->query("DROP TABLE IF EXISTS `se_i18n_keys`");

		} catch (Zend_Db_Exception $e) {
			$result = false;
			Logger::error("Failed to remove tables; " . $e->getMessage());
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	public static function isInstalled()
	{
		return self::checkTables();
	}

	/**
	 * @param string $language
	 */
	public static function getTranslationFile($language)
	{

	}
}