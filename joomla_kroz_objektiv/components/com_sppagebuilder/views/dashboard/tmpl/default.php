<?php

/**
 * @package SP Page Builder
 * @author JoomShaper http://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2023 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$doc = Factory::getDocument();
$addons_list = SpAddonsConfig::$addons;

foreach ($addons_list as &$addon) {

	$addon['visibility'] = true;

	if (!isset($addon['category']) || empty($addon['category'])) {
		$addon['category'] = 'General';
	}

	$addon_name = preg_replace('/^sp_/i', '', $addon['addon_name']);
	$class_name = 'SppagebuilderAddon' . ucfirst($addon_name);

	if (method_exists($class_name, 'getTemplate')) {
		$addon['js_template'] = true;
	}
}

unset($addon);

$accessLevels     = SpPgaeBuilderBase::getAccessLevelList(); // Access Levels
$languageList     = SpPgaeBuilderBase::getLanguageList(); // Access Levels
$pageCategories   = SpPgaeBuilderBase::getPageCategories(); // Page Categories

$params = ComponentHelper::getParams('com_sppagebuilder');
$doc->addScriptdeclaration('var disableGoogleFonts = ' . $params->get('disable_google_fonts', 0) . ';');

$doc->addScriptdeclaration('var pagebuilder_base = "' . Uri::root() . '";');

$doc->addScriptdeclaration('var addonsJSON = ' . json_encode($addons_list) . ';');
$doc->addScriptdeclaration('var addonsFromDB = ' . json_encode(SpAddonsConfig::loadAddonList()) . ';');
$doc->addScriptdeclaration('var initialState = [];');

$doc->addScriptdeclaration('var pageCategories=' . json_encode($pageCategories) . ';');
$doc->addScriptdeclaration('var accessLevels=' . json_encode($accessLevels) . ';');
$doc->addScriptdeclaration('var languageList=' . json_encode($languageList) . ';');
$doc->addScriptdeclaration('var sppbVersion="' . SppagebuilderHelperSite::getVersion() . '";');

$doc->addStylesheet(Uri::base(true) . '/components/com_sppagebuilder/assets/css/editor.css');
$doc->addStylesheet(Uri::base(true) . '/components/com_sppagebuilder/assets/css/dashboard.css');
$doc->addScript(Uri::base(true) . '/components/com_sppagebuilder/assets/js/vendors.js', ['version' => 'auto'], ['defer' => true]);
$doc->addScript(Uri::base(true) . '/components/com_sppagebuilder/assets/js/dashboard.js', ['version' => 'auto'], ['defer' => true]);

?>
<div id="builder-dashboard"></div>