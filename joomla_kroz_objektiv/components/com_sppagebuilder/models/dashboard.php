<?php

/**
 * @package SP Page Builder
 * @author JoomShaper http://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2023 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */


// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\MVC\Model\AdminModel;

class SppagebuilderModelDashboard extends AdminModel
{
	public function __construct($config = [])
	{
		parent::__construct($config);
	}

	public function getForm($data = [], $loadData = true)
	{
	}

	public function getTable($name = 'Dashboard', $prefix = 'SppagebuilderTable', $options = array())
	{
		return Table::getInstance($name, $prefix, $options);
	}

	public function checkLanguageIsInstalled($language = 'en-GB')
	{
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName(array('id', 'state', 'version')));
		$query->from($db->quoteName('#__sppagebuilder_languages'));
		$query->where($db->quoteName('lang_tag') . ' = ' . $db->quote($language));
		$db->setQuery($query);

		$result = $db->loadObject();

		if (!empty($result))
		{
			return $result;
		}

		return false;
	}

	public function storeLanguage($language)
	{
		$db = Factory::getDbo();
		$result = $this->checkLanguageIsInstalled($language->lang_tag);
		$version = $language->version;

		if ($result)
		{
			$values = array(
				'title' => $language->title,
				'description' => $language->description,
				'lang_key' => $language->lang_key,
				'version' => $language->version,
			);
			$version = $this->updateLanguage($values, $language->lang_key);
		}
		else
		{
			$values = array(
				$db->quote($language->title),
				$db->quote($language->description),
				$db->quote($language->lang_tag),
				$db->quote($language->lang_key),
				$db->quote($language->version),
				1
			);
			$this->insertLanguage($values);
		}

		return $version;
	}

	private function insertLanguage($values = array())
	{
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$columns = array('title', 'description', 'lang_tag', 'lang_key', 'version', 'state');
		$query
			->insert($db->quoteName('#__sppagebuilder_languages'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));

		$db->setQuery($query);
		$db->execute();
		$insertid = $db->insertid();

		return $insertid;
	}

	private function updateLanguage($values = array(), $lang_tag = 'en-GB')
	{
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$fields = array(
			$db->quoteName('title') . ' = ' . $db->quote($values['title']),
			$db->quoteName('description') . ' = ' . $db->quote($values['description']),
			$db->quoteName('lang_key') . ' = ' . $db->quote($values['lang_key']),
			$db->quoteName('version') . ' = ' . $db->quote($values['version']),
		);

		$conditions = array($db->quoteName('lang_key') . ' = ' . $db->quote($lang_tag));
		$query->update($db->quoteName('#__sppagebuilder_languages'))->set($fields)->where($conditions);
		$db->setQuery($query);
		$db->execute();

		return $values['version'];
	}

	/**
	 * Save new page information to DB.
	 *
	 * @param	array	$data	The page data to save.
	 *
	 * @return	array	The response array.
	 * @since	4.0.0
	 */
	public function savePage(array $data): array
	{
		$root = Uri::base();
		$root = new Uri($root);
		$user = Factory::getUser();
		$response = ['status' => false, 'data' => 'Something went wrong', 'redirect' => ''];

		if (!$user->authorise('core.create', 'com_sppagebuilder'))
		{
			$response = ['status' => false, 'data' => 'You are not authorized to create a page!', 'redirect' => ''];

			return $response;
		}

		$pageData = new \stdClass;

		foreach ($data as $key => $value)
		{
			$pageData->$key = $value;
		}

		$pageId = 0;

		try
		{
			$db = Factory::getDbo();
			$db->insertObject('#__sppagebuilder', $pageData, 'id');
			$pageId = $db->insertid();
		}
		catch (Exception $e)
		{
			$response = [
				'status' => false,
				'data' => $e->getMessage(),
				'redirect' => ''
			];

			return $response;
		}

		$response = [
			'status' => true,
			'data' => 'Page saved successfully!',
			'redirect' => Uri::base() . 'index.php?option=com_sppagebuilder&view=form&layout=edit&tmpl=component&id=' . $pageId
		];

		return $response;
	}

	public function getAddon($name)
	{
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName(array('id', 'name', 'status')));
		$query->from($db->quoteName('#__sppagebuilder_addonlist'));
		$query->where($db->quoteName('name') . ' = ' . $db->quote($name));
		$db->setQuery($query);

		$result = $db->loadObject();

		if (!empty($result))
		{
			return $result;
		}

		return false;
	}

	public function addAddon($name, $status = 1)
	{
		$db = Factory::getDbo();
		$addon = new stdClass();
		$addon->name = $name;
		$addon->ordering = 0;
		$addon->status = $status;

		$db->insertObject('#__sppagebuilder_addonlist', $addon);

		return $db->insertid();
	}

	public function toggleAddon($addon_name)
	{
		$addon = $this->getAddon($addon_name);

		if ($addon)
		{
			$status = $addon->status ? 0 : 1;
		}
		else
		{
			$status = 0; // assuming new addon is enabled
			$this->addAddon($addon_name, $status);
		}

		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$fields = array($db->quoteName('status') . ' = ' . $status);
		$conditions = array($db->quoteName('name') . ' = ' . $db->quote($addon_name));
		$query->update($db->quoteName('#__sppagebuilder_addonlist'))->set($fields)->where($conditions);
		$db->setQuery($query);
		$db->execute();

		return $status;
	}

	public function togglePageStatus($cid, $published)
	{
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$fields = array($db->quoteName('published') . ' = ' . $published);
		$conditions = array($db->quoteName('id') . ' = ' . $cid);

		$query->update($db->quoteName('#__sppagebuilder'))->set($fields)->where($conditions);

		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Toggle Integration
	 * 
	 * @return 	boolean
	 * @since 	4.0.0
	 */
	public function toggleIntegration($group = '', $name = '')
	{
		$enabled = PluginHelper::isEnabled($group, $name);
		$status = $enabled ? 0 : 1;

		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$fields = array($db->quoteName('enabled') . ' = ' . $status);

		$conditions = array(
			$db->quoteName('type') . ' = ' . $db->quote('plugin'),
			$db->quoteName('element') . ' = ' . $db->quote($name),
			$db->quoteName('folder') . ' = ' . $db->quote($group)
		);

		$query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
		$db->setQuery($query);
		$db->execute();

		return $status;
	}

	/**
	 * Delete page method.
	 *
	 * @return	boolean
	 * @since	4.0.0
	 */
	public function deletePage(int $id): bool
	{
		try
		{
			$db = Factory::getDbo();
			$query 	= $db->getQuery(true);
			$query->delete($db->quoteName('#__sppagebuilder'))
				->where($db->quoteName('id') . ' = ' . $id);
			$db->setQuery($query);
			$db->execute();
		}
		catch (Exception $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Get Menu List
	 *
	 * @return	array	The response array
	 * @since	4.0.0
	 */
	public function getMenuList(): array
	{
		$response = ['status' => false, 'data' => []];
		$code = 500;

		try
		{
			$db 	= Factory::getDbo();
			$query 	= $db->getQuery(true);
			$query->select('id, menutype, title')->from($db->quoteName('#__menu_types'))
				->where($db->quoteName('client_id') . ' = 0');
			$db->setQuery($query);

			$result = $db->loadObjectList();
			$data = [];

			if (!empty($result))
			{
				foreach ($result as $value)
				{
					$tmp = [
						'value' => $value->menutype,
						'label' => $value->title
					];
					$tmp = (object) $tmp;
					$data[] = $tmp;
				}
			}

			$response = ['status' => true, 'data' => $data];
			$code = 200;
		}
		catch (Exception $e)
		{
			$response = ['status' => false, 'message' => $e->getMessage()];
			$code = 500;
		}

		return [$response, $code];
	}

	/**
	 * Get Menu List
	 *
	 * @param	string	$menuType 	The menu type
	 * @return	array	The response array
	 * @since	4.0.0
	 */
	public function getParentItems(string $menuType, int $id = 0): array
	{
		$response = ['status' => false, 'data' => []];
		$code = 500;

		try
		{
			$db 	= Factory::getDbo();
			$query 	= $db->getQuery(true);
			$query->select('DISTINCT(a.id) AS value, a.title AS text, a.level, a.lft')
				->from($db->quoteName('#__menu', 'a'))
				->where($db->quoteName('a.menutype') . ' = ' . $db->quote($menuType))
				->where($db->quoteName('a.client_id') . ' = 0');

			if ($id > 0)
			{
				$query->join('LEFT', $db->quoteName('#__menu') . ' AS p ON p.id = ' . (int) $id)
					->where('NOT(a.lft >= p.lft AND a.rgt <= p.rgt)');
			}

			$query->where('a.published != -2')
				->order('a.lft ASC');

			$db->setQuery($query);

			$result = $db->loadObjectList();
			$data = [];

			if (!empty($result))
			{
				foreach ($result as $value)
				{
					$tmp = [
						'value' => $value->value,
						'label' => $value->text
					];
					$tmp = (object) $tmp;
					$data[] = $tmp;
				}
			}

			$rootItem = (object) ['value' => 1, 'label' => Text::_('COM_SPPAGEBUILDER_MENU_ITEM_ROOT')];
			array_unshift($data, $rootItem);

			$response = ['status' => true, 'data' => $data];
			$code = 200;
		}
		catch (Exception $e)
		{
			$response = ['status' => false, 'message' => $e->getMessage()];
			$code = 500;
		}

		return [$response, $code];
	}

	public function getMenuOrdering($parentId, $menuType)
	{
		try
		{
			$db 	= Factory::getDbo();
			$query 	= $db->getQuery(true);
			$query->select('a.id AS value, a.title AS label')
				->from($db->quoteName('#__menu', 'a'))
				->where($db->quoteName('a.published') . ' >= 0')
				->where($db->quoteName('a.parent_id') . ' = ' . (int) $parentId);

			if (!empty($menuType))
			{
				$query->where('a.menutype = ' . $db->quote($menuType));
			}
			else
			{
				$query->where('a.menutype != ' . $db->quote(''));
			}

			$query->order('a.lft ASC');
			$db->setQuery($query);

			$options = $db->loadObjectList();

			$options = array_merge(
				array(array('value' => '-1', 'label' => Text::_('COM_SPPAGEBUILDER_ITEM_FIELD_ORDERING_VALUE_FIRST'))),
				$options,
				array(array('value' => '-2', 'label' => Text::_('COM_SPPAGEBUILDER_ITEM_FIELD_ORDERING_VALUE_LAST')))
			);

			return $options;
		}
		catch (Exception $e)
		{
			return [];
		}
	}

	public function getMenuByPageId($pageId = 0)
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select(array('a.*'));
		$query->from('#__menu as a');
		$query->where('a.link = ' . $db->quote('index.php?option=com_sppagebuilder&view=page&id=' . $pageId));
		$query->where('a.client_id = 0');
		$db->setQuery($query);

		return $db->loadObject();
	}

	public function getMenuById($menuId = 0)
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select(array('a.*'));
		$query->from('#__menu as a');
		$query->where('a.id = ' . $menuId);
		$query->where('a.client_id = 0');
		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Count total number of pages.
	 *
	 * @param 	string 	$keyword	The search keyword.
	 *
	 * @return 	int
	 * @since 	4.0.0
	 */
	public function countTotalPages($keyword = ''): int
	{
		$db 	= Factory::getDbo();
		$query 	= $db->getQuery(true);

		$query->select('COUNT(*)')
			->from($db->quoteName('#__sppagebuilder'))
			// ->where($db->quoteName('published') . ' = 1')
			->where($db->quoteName('extension') . ' = ' . $db->quote('com_sppagebuilder'));


		if (!empty($keyword))
		{
			$query->where($db->quoteName('title') . ' REGEXP ' . $db->quote($keyword));
		}

		$db->setQuery($query);

		try
		{
			return $db->loadResult();
		}
		catch (Exception $e)
		{
			return 0;
		}

		return 0;
	}

	public function resetCheckin($id)
	{
		$db = Factory::getDbo();
		$nullDate = $db->getNullDate();

		$query = $db->getQuery(true)
				->update($db->quoteName('#__sppagebuilder'))
				->set($db->quoteName('checked_out') . ' = 0');
		$query->set($db->quoteName('checked_out_time') . ' = ' . $db->quote($nullDate));
		$query->where($db->quoteName('checked_out') . ' > 0');
		$query->where($db->quoteName('id') . ' = ' . $db->quote($id));
		$db->setQuery($query);

		try {
			$db->execute();
			return $db->getAffectedRows();
		} catch (\Exception $e) {
			return $e->getMessage();
		}

	}
}
