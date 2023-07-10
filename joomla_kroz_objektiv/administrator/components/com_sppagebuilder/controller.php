<?php

/**
 * @package SP Page Builder
 * @author JoomShaper http://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2023 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
//no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\MVC\Controller\BaseController;

class SppagebuilderController extends BaseController
{

	protected $default_view = 'builder';

	function display($cachable = false, $urlparams = false)
	{
		return parent::display();
	}

	public function resetcss()
	{
		$css_folder_path = JPATH_ROOT . '/media/com_sppagebuilder/css';
		if (Folder::exists($css_folder_path))
		{
			Folder::delete($css_folder_path);
		}
		die();
	}

	public function export()
	{
		$input  = Factory::getApplication()->input;
		$template = $input->get('template', '[]', 'RAW');
		$filename = 'template' . rand(10000, 99999);

		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename=$filename.json");
		header("Content-Type: application/json");
		header("Content-Transfer-Encoding: binary ");

		echo $template;
		die();
	}
}
