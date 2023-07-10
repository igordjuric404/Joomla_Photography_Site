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
use Joomla\CMS\Http\Http;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Filesystem\Folder;
use Joomla\Utilities\ArrayHelper;
use Joomla\Database\ParameterType;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\Controller\AdminController;

/**
 * Dashboard Controller class
 * 
 * @since 4.0.0
 */
class SppagebuilderControllerDashboard extends AdminController
{
    public function __construct($config = array())
    {
        parent::__construct($config);

        // check have access
        $user = Factory::getUser();
        $authorised = $user->authorise('core.admin', 'com_sppagebuilder');

        if (!$authorised) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			return;
        }

        // Check CSRF Token
        // Session::checkToken() or die('Restricted Access');
    }

    public function getModel($name = 'Dashboard', $prefix = '', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Load all the pages data.
     *
     * @return    void
     * @since    4.0.0
     */
    public function loadPages()
    {
        $input = Factory::getApplication()->input;
        $limit = $input->getInt('limit', 10);
        $offset = $input->getInt('offset', 0);
        $filter = $input->get('filter', '', 'STRING');
        $sortBy = $input->get('sortBy', '', 'STRING');
        $access = $input->get('access', '', 'STRING');
        $catid = $input->getInt('catid', 0);
        $language = $input->get('filter-lang', '', 'STRING');
        $published = $input->get('published', '', 'STRING');
        $model = $this->getModel();

        $response = [
            'status' => false,
            'data' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG"),
        ];

        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true);

            $query->select('p.id, p.title, p.extension_view, p.catid, p.published, p.created_on, p.created_by, p.language, p.hits, p.checked_out');
            $query->select('c.title as category');

            $query->from($db->quoteName('#__sppagebuilder', 'p'));
            $query->where($db->quoteName('p.extension') . ' = ' . $db->quote('com_sppagebuilder'));

            $query->select('l.title AS language_title')
			->join('LEFT', $db->quoteName('#__languages', 'l') . ' ON l.lang_code = p.language');

            $query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON (' . $db->quoteName('p.catid') . ' = ' . $db->quoteName('c.id') . ')');

            $query->select('ug.title AS access_title')
			->join('LEFT',$db->quoteName('#__viewlevels', 'ug') .' ON ('. $db->quoteName('ug.id') .' = '. $db->quoteName('p.access') .')');

            if (is_numeric($published))
            {
                $query->where($db->quoteName('p.published') .' = '  . $db->quote($published));
            }
            else
            {
                $query->where($db->quoteName('p.published') .' IN (0, 1)');
            }

            if (!empty($access))
            {
                $query->where($db->quoteName('p.access').' = ' . $db->quote($access));
            }

            if (!empty($catid))
            {
                $query->where($db->quoteName('p.catid').' = ' . $db->quote($catid));
            }

            if (!empty($language))
            {
                $query->whereIn($db->quoteName('p.language'), [$language], ParameterType::STRING);
            }

            if (!empty($limit)) {
                $query->setLimit($limit, $offset);
            }

            if (!empty($filter))
            {
                $filter = preg_replace("@\s+@", ' ', $filter);
                $filter = explode(' ', $filter);
                $filter = array_filter($filter, function ($word) {
                    return !empty($word);
                });
                $filter = implode('|', $filter);
                $query->where($db->quoteName('p.title') . ' REGEXP ' . $db->quote($filter));
            }

            if (!empty($sortBy))
            {
                list($ordering, $orderDirection) = explode(' ', $sortBy);
                $query->order($db->quoteName($ordering) . ' ' . $orderDirection);
            }

            $db->setQuery($query);

            $results = $db->loadObjectList();

            if (!empty($results)) {
                if (!class_exists('SppagebuilderHelperRoute')) {
                    require_once JPATH_BASE . '/components/com_sppagebuilder/helpers/route.php';
                }

                foreach ($results as &$result) {
                    if ($result->created_on) {
                        $result->created = (new DateTime($result->created_on))->format('j F, Y');
                        unset($result->created_on);
                    }

                    if (!empty($result->created_by)) {
                        $result->author = Factory::getUser($result->created_by)->name;
                    }

                    if (empty($result->category)) {
                        $result->category = 'Uncategorised';
                    }

                    $result->url = SppagebuilderHelperRoute::getFormRoute($result->id, $result->language);
                    $result->preview = SppagebuilderHelperRoute::getPageRoute($result->id, $result->language);
                }

                unset($result);
            }

            $response = [
                'status' => true,
                'data' => $results,
                'total' => $model->countTotalPages($filter),
            ];
        } catch (Exception $e) {
            $response = [
                'status' => false,
                'data' => $e->getMessage(),
                'total' => 0,
            ];
        }

        echo json_encode($response);
        die();
    }

    public function duplicatePage()
    {
        $input = Factory::getApplication()->input;
        $model = $this->getModel();

        $pageId = $input->getInt('id', '', 'int');

        try {
           $db = Factory::getDbo();
           $query = $db->getQuery(true);
           $query->select("*");
           $query->from($db->qn('#__sppagebuilder'));
           $query->where($db->qn('id') . '='. $db->q($pageId));
           $db->setQuery($query);

           $results = $db->loadObject();
           
           if (!empty($results))
           {
            $results->title =  $this->pageGenerateNewTitle($results->title);
            $results->hits = 0;
            $results->id = '';
            $db->insertObject('#__sppagebuilder', $results, 'id');
            $model->checkin($pageId);
            $response = ['status' => true, 'id' => $results->id, 'message' => Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_PAGE_DUPLICATED")];
            $this->sendResponse($response, 200);
           }
           
        } catch (Exception $e) {
            $response = ['status' => false, 'message' => $e->getMessage()];
            $this->sendResponse($response, 400);
        }

    }

    /**
     * Generate page title
     *
     * @param string $title current page title.
     * 
     * @return void
     */
    public function pageGenerateNewTitle($title)
	{
        $pageTable = $this->getModel()->getTable();

        while( $pageTable->load(array('title'=>$title)) )
        {
            $m = null;
            if (preg_match('#\((\d+)\)$#', $title, $m))
            {
                $title = preg_replace('#\(\d+\)$#', '('.($m[1] + 1).')', $title);
            }
            else
            {
                $title .= ' (2)';
            }
        }

        return $title;
    }
    /**
     * Delete page method.
     *
     * @return    void
     * @since    4.0.0
     */
    public function deletePage()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $user = Factory::getUser();
        $model = $this->getModel();

        $token = Session::getFormToken();
        $headerToken = $input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum');

        if ($token !== $headerToken)
        {
            $response = ['status' => false, 'message' => Text::_("JLIB_ENVIRONMENT_SESSION_EXPIRED")];
            $this->sendResponse($response, 400);
        }

        $id = $input->get('id', 0, 'INT');

        $authorised = $user->authorise('core.delete', 'com_sppagebuilder');

        if (!$authorised)
        {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_UNAUTHORIZED")];
            $this->sendResponse($response, 403);
        }

        if (!$model->delete($id))
        {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_DELETE")];
            $this->sendResponse($response, 400);
        }

        $response = ['status' => true, 'message' => Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_PAGE_DELETE")];
        $this->sendResponse($response, 200);
    }

    /**
     * Order Page List function
     *
     * @return void
     * 
     * @since 4.0.0
     */
    public function orderPages()
    {
        $input = Factory::getApplication()->input;
        $model = $this->getModel();

        $pks = $input->json->get('pks', [], 'ARRAY');
        $orders = $input->json->get('orders', [], 'ARRAY');

        $pks = ArrayHelper::toInteger($pks);
        $orders = ArrayHelper::toInteger($orders);

        $response = ['status' => false, 'data' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG")];

        try {
            if (!$model->saveorder($pks, $orders)) {
                $response['data'] = Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_FAILS");
            } else {
                $response['status'] = true;
                $response['data'] = Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_ORDER");
            }
        } catch (Exception $e) {
            $response['data'] = $e->getMessage();
        }

        echo json_encode($response);
        die();
    }

    /**
     * Get the list of integrations
     *
     * @return    void
     * @since    4.0.0
     */
    public function integrationList()
    {
        return [
            'content' => [
                'title' => Text::_("COM_SPPAGEBUILDER_JOOMLA_ARTICLE"),
                'group' => 'content',
                'name' => 'sppagebuilder',
                'view' => 'article',
                'id_alias' => 'id',
                'thumbnail' => Uri::root() . 'components/com_sppagebuilder/assets/images/joomla_article.jpg',
                'enabled' => PluginHelper::isEnabled('content', 'sppagebuilder'),
            ],

            'spsimpleportfolio' => [
                'title' => Text::_("COM_SPPAGEBUILDER_SP_SIMPLE_PORTFOLIO"),
                'group' => 'spsimpleportfolio',
                'name' => 'sppagebuilder',
                'view' => 'item',
                'id_alias' => 'id',
                'frontend_only' => true,
                'thumbnail' => Uri::root() . 'components/com_sppagebuilder/assets/images/sp_simple_portfolio.jpg',
                'enabled' => PluginHelper::isEnabled('spsimpleportfolio', 'sppagebuilder'),
            ],

            'k2' => [
                'title' => 'K2',
                'group' => 'k2',
                'name' => 'sppagebuilder',
                'view' => 'item',
                'id_alias' => 'cid',
                'thumbnail' => Uri::root() . 'components/com_sppagebuilder/assets/images/k2.jpg',
                'enabled' => PluginHelper::isEnabled('k2', 'sppagebuilder'),
            ],
        ];
    }

    /**
     * Load Integrations from the list
     *
     * @return    void
     * @since    4.0.0
     */
    public function loadIntegrations()
    {
        $response = [
            'status' => false,
            'data' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG"),
        ];

        try {
            $results = (array) $this->integrationList();

            /** @TODO: this filter will be removed for getting all the integrations. */
            $results = array_filter($results, function ($item) {
                return $item['group'] !== 'k2';
            });

            $response = [
                'status' => true,
                'data' => $results,
            ];
        } catch (Exception $e) {
            $response = [
                'status' => false,
                'data' => $e->getMessage(),
            ];
        }

        echo json_encode($response);
        die();
    }

    /**
     * Toggle Page status
     * 
     * @return void
     * 
     * @since 4.0.0
     */
    public function togglePage()
    {
        $user = Factory::getUser();
        $model = $this->getModel();

        $pageData = $this->input->json->get('pageData', [], 'ARRAY');

        if (empty($pageData['cid']))
        {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG")];
            $this->sendResponse($response, 400);
        }

        $authorised = $user->authorise('core.admin', 'com_sppagebuilder');

        if (!$authorised)
        {
            $response = ['status' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')];
            $this->sendResponse($response, 403);
        }
        $model->togglePageStatus($pageData['cid'], (int) $pageData['published']);
        $response = ['status' => true, 'message' => Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_PAGE_PUBLISHED"), 'published' => $pageData['published']];
        $this->sendResponse($response, 200);
    }

    /**
     * Toggle integration
     *
     * @return    void
     * @since    4.0.0
     */
    public function toggleIntegration()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $user = Factory::getUser();
        $model = $this->getModel();

        $integration_group = $input->get('integration', null, 'STRING');

        if (empty($integration_group)) {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_INVALID_INTEGRATION")];
            $this->sendResponse($response, 400);
        }

        $authorised = $user->authorise('core.admin', 'com_sppagebuilder');

        if (!$authorised) {
            $response = ['status' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')];
            $this->sendResponse($response, 403);
        }

        $integrations = $this->integrationList();

        if (isset($integrations[$integration_group])) {
            $integration = $integrations[$integration_group];

            $result = $model->toggleIntegration($integration['group'], $integration['name']);
            $integration['enabled'] = $result;
            $message = $integration['title'] . ' Integration is ' . ($result ? 'Enabled' : 'Disabled') . ' Successfully';

            $response = ['status' => true, 'message' => $message, 'data' => $integration];
            $this->sendResponse($response, 200);
        } else {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_UNABLE_FIND_INTEGRATION")];
            $this->sendResponse($response, 404);
        }

        $response = ['status' => false, 'data' => $integration_group, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_ENABLED_OR_DISABLE_INTEGRATION")];
        $this->sendResponse($response, 500);
    }

    /**
     * Get the list of languages
     *
     * @return    void
     * @since    4.0.0
     */
    public function loadLanguages()
    {
        $model = $this->getModel();

        $response = [
            'status' => false,
            'data' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG"),
        ];

        try {
            $http = new Http;
            $language_api = 'https://sppagebuilder.com/api/languages/languages.json';
            $languageResponse = $http->get($language_api);
            $languagesData = $languageResponse->body;

            if ($languageResponse->code !== 200) {
                $response = [
                    'status' => false,
                    'message' => $languagesData->error->message,
                ];

                die(json_encode($report));
            }

            $languages = json_decode($languagesData);

            if (count((array) $languages)) {
                $results = new stdClass;
                foreach ($languages as $key => $item) {
                    $item->thumbnail = URI::root() . 'media/mod_languages/images/' . strtolower(str_ireplace('-', '_', $item->lang_tag)) . '.gif';
                    $installed = $model->checkLanguageIsInstalled($item->lang_tag);
                    $item->state = -1;
                    $item->status = Text::_("COM_SPPAGEBUILDER_DASHBOARD_PAGES_LANGUAGE_STATUS_NOT_INSTALLED");
                    $item->updatable = false;

                    if (is_object($installed)) {
                        $item->state = $installed->state;

                        if ($item->state == 1) {
                            $item->status = Text::_("COM_SPPAGEBUILDER_DASHBOARD_PAGES_LANGUAGE_STATUS_ACTIVATED");
                        } else {
                            $item->status = Text::_("COM_SPPAGEBUILDER_DASHBOARD_PAGES_LANGUAGE_STATUS_INSTALLED");;
                        }

                        if ($item->version > $installed->version) {
                            $item->updatable = true;
                        }
                    }

                    $results->$key = $item;
                }

                $response = [
                    'status' => true,
                    'data' => $results,
                ];
            } else {
                $response = [
                    'status' => false,
                    'data' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_NO_RESULT_FOUND"),
                ];
            }
        } catch (Exception $e) {
            $response = [
                'status' => false,
                'data' => $e->getMessage(),
            ];
        }

        echo json_encode($response);
        die();
    }

    /**
     * Get the list of addons
     *
     * @return    array
     * @since    4.0.0
     */
    public function getAddonList()
    {
        $model = $this->getModel();
        SppagebuilderHelperSite::loadLanguage(true);

        if (!class_exists('SpAddonsConfig')) {
            require_once JPATH_COMPONENT . '/builder/classes/base.php';
            require_once JPATH_COMPONENT . '/builder/classes/config.php';
        }

        SpPgaeBuilderBase::loadAddons();

        $addons = array();
        $addonList = SpAddonsConfig::$addons;

        foreach ($addonList as $addonListItem) {
            if (isset($addonListItem['inline'])) {
                unset($addonListItem['inline']);
            }

            if (isset($addonListItem['attr'])) {
                unset($addonListItem['attr']);
            }

            $addon_name = SppagebuilderHelperSite::sanitize_addon_name($addonListItem['addon_name']);
            $addonListItem['addon_name'] = $addon_name;

            $dbAddon = $model->getAddon($addon_name);

            if (!empty($dbAddon)) {
                $addonListItem['status'] = $dbAddon->status;
            } else {
                $addonListItem['status'] = 1;
            }

            $addons[] = $addonListItem;
        }

        return $addons;
    }

    /**
     * Load all addons
     *
     * @return    void
     * @since    4.0.0
     */
    public function loadAddons()
    {

        $addons = $this->getAddonList();
        $results = array();

        foreach ($addons as &$addon) {
            if (!isset($addon['category'])) {
                $addon['category'] = 'General';
            }

            $addonCategory = $addon['category'];
            $results[$addonCategory][] = $addon;
        }

        unset($addon);

        $sortedKeys = ['Structure', 'General', 'Content', 'Media', 'Slider'];
        $structureKeys = ['row', 'columns', 'div'];
        $structures = &$results['Structure'] ?? [];

        if (!empty($structures) && \is_array($structures)) {
            usort($structures, function ($a, $b) use ($structureKeys) {
                $indexA = array_search($a['addon_name'], $structureKeys);
                $indexB = array_search($b['addon_name'], $structureKeys);

                return $indexA - $indexB;
            });
        }

        $results = array_merge(array_flip($sortedKeys), $results);

        $response = [
            'status' => true,
            'data' => $results,
        ];

        echo json_encode($response);
        die();
    }

    /**
     * Toggle Addon
     *
     * @return    void
     * @since    4.0.0
     */
    public function toggleAddon()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $user = Factory::getUser();
        $model = $this->getModel();

        $addon_name = SppagebuilderHelperSite::sanitize_addon_name($input->get('addon', null, 'STRING'));

        if (empty($addon_name)) {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_INVALID_ADDON_NAME")];
            $this->sendResponse($response, 400);
        }

        $authorised = $user->authorise('core.admin', 'com_sppagebuilder');

        if (!$authorised) {
            $response = ['status' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')];
            $this->sendResponse($response, 403);
        }

        $addonList = $this->getAddonList();
        $addons = array();

        foreach ($addonList as $addonListItem) {
            $addons[SppagebuilderHelperSite::sanitize_addon_name($addonListItem['addon_name'])] = $addonListItem;
        }

        if (isset($addons[$addon_name])) {
            $addon = $addons[$addon_name];
            $result = $model->toggleAddon($addon_name);
            $addon['status'] = $result;

            $message = $addon['title'] . ' Addon is ' . ($result ? 'enabled' : 'disabled') . ' successfully';

            if (!isset($addon['category'])) {
                $addon['category'] = 'General';
            }

            $response = ['status' => true, 'message' => $message, 'data' => $addon];
            $this->sendResponse($response, 200);
        } else {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_INVALID_ADDON")];
            $this->sendResponse($response, 404);
        }

        $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_ENABLED_OR_DISABLE_ADDON")];
        $this->sendResponse($response, 500);
    }

    /**
     * Save new page by HTTP request.
     *
     * @return    void
     * @since    4.0.0
     */
    public function savePage()
    {
        $data = [];
        $user = Factory::getUser();
        $model = $this->getModel();
        $app = Factory::getApplication();
        $input = $app->input;

        $data['title'] = $input->json->get('title', '', 'STRING');
        $data['text'] = '[]';
        $data['css'] = '';
        $data['catid'] = 0;
        $data['language'] = '*';
        $data['access'] = 1;
        $data['created_on'] = Factory::getDate()->toSql();
        $data['created_by'] = $user->id;
        
        $response = $model->savePage($data);

        $app->setHeader('status', 200, true);
        $app->sendHeaders();
        echo new JsonResponse($response);
        $app->close();
    }

    /**
     * Install language package.
     *
     * @return    void
     * @since    4.0.0
     */
    public function installLanguage()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $user = Factory::getUser();
        $model = $this->getModel();

        $lang = $input->get('languageCode', null, 'STRING');

        if (empty($lang)) {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_LANGUAGE_CODE")];
            $this->sendResponse($response, 400);
        }

        $authorised = $user->authorise('core.admin', 'com_sppagebuilder');

        if (!$authorised) {
            $response = ['status' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')];
            $this->sendResponse($response, 403);
        }

        $language_api = 'https://sppagebuilder.com/api/languages/languages.json';

        if (ini_get('allow_url_fopen')) {
            $output = file_get_contents($language_api);
        } elseif (extension_loaded('curl')) {
            $output = $this->getCurlData($language_api);
        } else {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_LANGUAGE_URL_ENABLE")];
            $this->sendResponse($response, 400);
        }

        $languages = !empty($output) ? json_decode($output) : [];

        if (!empty($languages->$lang->downloads->source)) {
            $downloadURL = $languages->$lang->downloads->source;
            $language = $languages->$lang;
        } else {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_UNABLED_DWON_LANGUAGE")];
            $this->sendResponse($response, 404);
        }

        $packageFile = InstallerHelper::downloadPackage($downloadURL);

        if (empty($packageFile)) {
            $response = ['status' => false, 'message' => Text::_('COM_INSTALLER_MSG_INSTALL_INVALID_URL')];
            $this->sendResponse($response, 404);
        }

        $config = Factory::getConfig();
        $tmpPath = $config->get('tmp_path');
        $package = InstallerHelper::unpack($tmpPath . '/' . $packageFile, true);

        $installer = Installer::getInstance();

        if ($installer->install($package['dir'])) {
            $language->state = 1;
            $language->status = 'Activated';
            $response = ['status' => true, 'message' => Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_LANGUAGE_INSTALL"), 'data' => $language];
            $model->storeLanguage($language);
            $this->sendResponse($response, 200);
        }

        $response = ['status' => false, 'data' => $language, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_FAILED_LANGUAGE_INSTALL")];
        $this->sendResponse($response, 500);
    }

    /**
     * Menu Menu List.
     *
     * @return    void
     * @since    4.0.0
     */
    public function getMenuList()
    {
        $model = $this->getModel();
        list($response, $statusCode) = $model->getMenuList();

        $this->sendResponse($response, $statusCode);
    }

    /**
     * Get parent Items
     *
     * @return    void
     * @since    4.0.0
     */
    public function getParentItems()
    {
        $input = Factory::getApplication()->input;
        $model = $this->getModel();

        $menuType = $input->get('menutype', 'mainmenu', 'STRING');
        $id = $input->get('id', 0, 'INT');

        list($response, $statusCode) = $model->getParentItems($menuType, $id);

        $this->sendResponse($response, $statusCode);
    }

    /**
     * Get Menu Ordering.
     *
     * @return void
     * 
     * @since 4.0.0
     */
    public function getMenuOrdering()
    {
        $input = Factory::getApplication()->input;
        $model = $this->getModel();

        $parentId = $input->get('parent_id', 0, 'INT');
        $menuType = $input->get('menutype', 'mainmenu', 'STRING');

        $this->sendResponse($model->getMenuOrdering($parentId, $menuType), 200);
    }

    /**
     * Get Menu Page Id.
     *
     * @return void
     * 
     * @since 4.0.0
     */
    public function getMenuByPageId()
    {
        $app = Factory::getApplication('site');
        $input = $app->input;
        $model = $this->getModel();
        $pageId = $input->get('id', 0, 'INT');

        $menu = $model->getMenuByPageId($pageId);
        $response = ['status' => true, 'data' => $menu];

        $this->sendResponse($response, 200);
    }

    /**
     * Add Item Into a Menu.
     *
     * @return void
     * 
     * @since 4.0.0
     */
    public function addToMenu()
    {
        $app = Factory::getApplication('site');
        $input = $app->input;
        $user = Factory::getUser();
        $model = $this->getModel();

        $pageId = $input->json->get('page_id', 0, 'INT');
        $menuId = $input->json->get('menu_id', 0, 'INT');
        $parentId = $input->json->get('parent_id', 0, 'INT');
        $menuType = $input->json->get('menu_type', 'mainmenu', 'STRING');
        $title = $input->json->get('title', '', 'STRING');
        $alias = $input->json->get('alias', OutputFilter::stringURLSafe($title), 'STRING');
        $menuOrdering = $input->json->get('ordering', 0, 'INT');

        $componentId = ComponentHelper::getComponent('com_sppagebuilder')->id;

        $menu = $model->getMenuById($menuId);
        $home = (isset($menu->home) && $menu->home) ? $menu->home : 0;
        $link = 'index.php?option=com_sppagebuilder&view=page&id=' . (int) $pageId;

        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/models');
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/tables');

        $menuModel = $this->getModel('Item', 'MenusModel');

        $menuData = array(
            'id' => (int) $menuId,
            'link' => $link,
            'parent_id' => (int) $parentId,
            'menutype' => htmlspecialchars($menuType),
            'title' => htmlspecialchars($title),
            'alias' => htmlspecialchars($alias),
            'type' => 'component',
            'published' => 1,
            'language' => '*',
            'component_id' => $componentId,
            'menuordering' => (int) $menuOrdering,
            'home' => (int) $home,
        );

        try {
            $menuModel->save($menuData);
            $response = ['status' => true, 'message' => Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_PAGE_ADDED")];
            $this->sendResponse($response, 200);
        } catch (Exception $e) {
            $response = ['status' => false, 'message' => $e->getMessage()];
            $this->sendResponse($response, 500);
        }
    }

    /**
     * Load the component params.
     *
     * @return    void
     * @since    4.0.0
     */
    public function loadComponentParams()
    {
        $params = ComponentHelper::getParams('com_sppagebuilder');
        $response = ['status' => true, 'data' => $params];
        $this->sendResponse($response, 200);
    }

    /**
     * Save settings data of the component sppagebuilder.
     *
     * @return    void
     * @since    4.0.0
     */
    public function saveSettings()
    {
        $app = Factory::getApplication('site');
        $input = $app->input;

        $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG")];

        $productionMode = $input->json->get('production_mode', 0, 'INT');
        $gmapApi = $input->json->get('gmap_api', '', 'STRING');
        $igToken = $input->json->get('ig_token', '', 'STRING');
        $fontAwesome = $input->json->get('fontawesome', 1, 'INT');
        $disableGoogleFonts = $input->json->get('disable_google_fonts', 0, 'INT');
        $lazyLoadimg = $input->json->get('lazyloadimg', 0, 'INT');
        $lazyPlaceholder = $input->json->get('lazyplaceholder', '', 'STRING');
        $disableAnimateCSS = $input->json->get('disableanimatecss', 0, 'INT');
        $disableCSS = $input->json->get('disablecss', 0, 'INT');
        $disableOG = $input->json->get('disable_og', 0, 'INT');
        $fbAppID = $input->json->get('fb_app_id', '', 'STRING');
        $disableTc = $input->json->get('disable_tc', 0, 'INT');
        $joomshaperEmail = $input->json->get('joomshaper_email', '', 'STRING');
        $joomshaperLicenseKey = $input->json->get('joomshaper_license_key', '', 'STRING');

        $params = ComponentHelper::getParams('com_sppagebuilder');
        $componentId = ComponentHelper::getComponent('com_sppagebuilder')->id;

        $params->set('production_mode', $productionMode);
        $params->set('gmap_api', $gmapApi);
        $params->set('ig_token', $igToken);
        $params->set('fontawesome', $fontAwesome);
        $params->set('disable_google_fonts', $disableGoogleFonts);
        $params->set('lazyloadimg', $lazyLoadimg);
        $params->set('lazyplaceholder', $lazyPlaceholder);
        $params->set('disableanimatecss', $disableAnimateCSS);
        $params->set('disablecss', $disableCSS);
        $params->set('disable_og', $disableOG);
        $params->set('fb_app_id', $fbAppID);
        $params->set('disable_tc', $disableTc);
        $params->set('joomshaper_email', $joomshaperEmail);
        $params->set('joomshaper_license_key', $joomshaperLicenseKey);

        if (!empty($joomshaperEmail) && !empty($joomshaperLicenseKey)) {
            if (!$this->updateLicenseKey($joomshaperEmail, $joomshaperLicenseKey)) {
                $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_FAILED_LICESE_KEY")];
                $this->sendResponse($response, 500);
            }
        }

        $table = Table::getInstance('extension');

        if (!$table->load($componentId)) {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_FAILED_LOAD_EXTENSION")];
            $this->sendResponse($response, 500);
        }

        $table->params = json_encode($params);

        if (!$table->store()) {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_FAILED_STORE_EXTENSION")];
            $this->sendResponse($response, 500);
        }

        $response = ['status' => true, 'message' => Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_SETTINGS")];

        $this->sendResponse($response, 200);
    }

    /**
     * Update license key.
     *
     * @param string $email
     * @param string $key
     * @return void
     * 
     * @since 4.0.0
     */
    private function updateLicenseKey($email, $key)
    {
        $value = 'joomshaper_email=' . urlencode($email);
        $value .= '&amp;joomshaper_license_key=' . urlencode($key);

        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('extra_query') . ' = ' . $db->quote($value),
            $db->quoteName('last_check_timestamp') . ' = ' . $db->quote('0'),
        ];

        $query->update($db->quoteName('#__update_sites'))
            ->set($fields)
            ->where($db->quoteName('name') . ' = ' . $db->quote('SP Page Builder'));

        $db->setQuery($query);

        try {
            $db->execute();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Save IG Token
     *
     * @return    void
     * @since    4.0.0
     */
    public function saveIgToken()
    {
        $app = Factory::getApplication('site');
        $input = $app->input;

        $igToken = $input->json->get('ig_token', [], 'ARRAY');

        $params = ComponentHelper::getParams('com_sppagebuilder');
        $componentId = ComponentHelper::getComponent('com_sppagebuilder')->id;

        $_token = json_decode($params->get('ig_token'), true);
        $token = array_merge($_token, $igToken);

        $params->set('ig_token', json_encode($token));

        $table = Table::getInstance('extension');

        if (!$table->load($componentId)) {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_FAILED_LOAD_EXTENSION")];
            $this->sendResponse($response, 500);
        }

        $table->params = json_encode($params);

        if (!$table->store()) {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG_FOR_FAILED_STORE_EXTENSION")];
            $this->sendResponse($response, 500);
        }

        $response = ['status' => true, 'message' => Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_SETTINGS")];

        $this->sendResponse($response, 200);
    }

    /**
     * Reset the CSS files.
     *
     * @return    void
     * @since    4.0.0
     */
    public function resetCss()
    {
        $css_folder_path = JPATH_ROOT . '/media/com_sppagebuilder/css';

        if (Folder::exists($css_folder_path)) {
            Folder::delete($css_folder_path);
        }

        $response = ['status' => true, 'message' => Text::_("COM_SPPAGEBUILDER_SUCCESS_MSG_FOR_CSS_RESET")];
        $this->sendResponse($response, 200);
    }

    /**
     * Get data using CURL.
     *
     * @param    string    $url    The terminal point where to fetch data.
     *
     * @return    mixed    The data fetched by CURL.
     * @since    4.0.0
     */
    private function getCurlData($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    /**
     * Send JSON Response to the client.
     *
     * @param    array    $response    The response array or data.
     * @param    int        $statusCode    The status code of the HTTP response.
     *
     * @return    void
     * @since    4.0.0
     */
    private function sendResponse($response, int $statusCode = 200): void
    {
        $app = Factory::getApplication();
        $app->setHeader('status', $statusCode, true);
        $app->sendHeaders();
        echo new JsonResponse($response);
        $app->close();
    }
    
    /**
     * Item Checked In
     *
     * @return void
     */
    public function resetCheckin()
    {
        $valid = Session::checkToken();

        if (!$valid) return;

        $user = Factory::getUser();
        $id = $this->input->json->get('cid');
        if (empty($id))
        {
            $response = ['status' => false, 'message' => Text::_("COM_SPPAGEBUILDER_ERROR_MSG")];
            $this->sendResponse($response, 400);
        }

        $authorised = $user->authorise('core.admin', 'com_sppagebuilder');

        if (!$authorised)
        {
            $response = ['status' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')];
            $this->sendResponse($response, 403);
        }

        $model  = $this->getModel();
        $model->resetCheckin($id);
        $response = ['status' => true, 'message' => Text::_("COM_SPPAGEBUILDER_ITEMS_CHECKED_IN")];
        $this->sendResponse($response, 200);

    }
}
