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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;


class SppagebuilderViewDashboard extends HtmlView
{
	protected $item;
	/**
	 * Display function for the view dashboard.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 * @since	4.0.0
	 */
	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$user = Factory::getUser();

		/** Securely login the user before go through. */
		AuthHelper::loginBeforePassThrough();

		/** If not logged in then stop proceeding. */
		if (!$user->id)
		{
			header('Location: ' . Uri::root() . 'index.php');
			exit;
		}

		$isAuthorised = $user->authorise('core.admin', 'com_sppagebuilder') || $user->authorise('core.manage', 'com_sppagebuilder') || $user->authorise('core.edit', 'com_sppagebuilder') || $user->authorise('core.edit.own', 'com_sppagebuilder');

		if (!$isAuthorised)
		{
			$app->enqueueMessage(Text::_('COM_SPPAGEBUILDER_ERROR_EDIT_PERMISSION'), 'error');
			$app->redirect('/', 403);
			return false;
		}
		SppagebuilderHelperSite::loadLanguage();

		$this->setDocumentTitle('');

		parent::display($tpl);
	}
}
