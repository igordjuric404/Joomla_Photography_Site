<?php

/**
 * @package SP Page Builder
 * @author JoomShaper http://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2023 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
//no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_COMPONENT . '/helpers/image.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\FormController;

class SppagebuilderControllerMedia extends FormController
{

	public function getModel($name = 'Media', $prefix = '', $config = array('ignore_request' => true))
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Rename the media file
	 *
	 * @return void
	 */
	public function renameMedia()
	{
		$app 		= Factory::getApplication('site');
		$input 		= $app->input;

		$id = $input->json->get('id', 0, 'INT');
		$title = $input->json->get('title', '', 'STR');
		$path = $input->json->get('path', '', 'STR');
		$thumb = $input->json->get('thumb', '', 'STR');

		$mediaType = empty($id) ? 'folder' : 'DB';

		$data = new \stdClass;
		$data->id = $id;
		$data->title = $title;
		$data->path = $this->replacePathByTitle($path, $title);
		$data->thumb = $this->replacePathByTitle($thumb, $title);

		$response = ['data' => Text::_("COM_SPPAGEBUILDER_MEDIA_MANAGER_MEDIA_RENAME_ERROR"), 'status' => false, 'code' => 500];

		try
		{
			if ($mediaType === 'DB')
			{
				$db 	= Factory::getDbo();
				$db->updateObject('#__spmedia', $data, 'id');
			}

			\rename(JPATH_ROOT . '/' . $path, JPATH_ROOT . '/' . $data->path);
			\rename(JPATH_ROOT . '/' . $thumb, JPATH_ROOT . '/' . $data->thumb);

			$response = [
				'data' => Text::_("COM_SPPAGEBUILDER_MEDIA_MANAGER_MEDIA_RENAME_SUCCESS"),
				'status' => true,
				'code' => 200
			];
		}
		catch (Exception $e)
		{
			$response = [
				'data' => $e->getMessage(),
				'status' => false,
				'code' => 500
			];
		}

		$code = $response['code'];
		unset($response['code']);

		$app->setHeader('status', $code, true);
		$app->sendHeaders();
		echo new JsonResponse($response);
		$app->close();
	}

	private function replacePathByTitle($path, $title)
	{
		$fileName = pathinfo($path, PATHINFO_FILENAME);
		$basename = basename($path);

		$newFile = str_replace($fileName, $title, $basename);

		return str_replace($basename, $newFile, $path);
	}

	/**
	 * Upload media file function
	 *
	 * @return string
	 * 
	 * @since 4.0.0
	 */
	public function upload_media()
	{
		$model  = $this->getModel();
		$user 	= Factory::getUser();
		$input  = Factory::getApplication()->input;

		if (isset($_FILES['file']) && $_FILES['file'])
		{
			$file  = $_FILES['file'];

			$dir  = $input->post->get('folder', '', 'PATH');
			$report = array();

			$authorised = $user->authorise('core.edit', 'com_sppagebuilder') || $user->authorise('core.edit.own', 'com_sppagebuilder');

			if ($authorised !== true)
			{
				$report['status'] = false;
				$report['output'] = Text::_('JERROR_ALERTNOAUTHOR');
				echo json_encode($report);
				die();
			}

			if (count((array) $file))
			{
				if ($file['error'] == UPLOAD_ERR_OK)
				{
					$error = false;
					$params = ComponentHelper::getParams('com_media');
					$contentLength = (int) $_SERVER['CONTENT_LENGTH'];
					$mediaHelper = new MediaHelper;
					$postMaxSize = $mediaHelper->toBytes(ini_get('post_max_size'));
					$memoryLimit = $mediaHelper->toBytes(ini_get('memory_limit'));

					// Check for the total size of post back data.
					if (($postMaxSize > 0 && $contentLength > $postMaxSize) || ($memoryLimit != -1 && $contentLength > $memoryLimit))
					{
						$report['status'] = false;
						$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_MEDIA_TOTAL_SIZE_EXCEEDS');
						$error = true;
						echo json_encode($report);
						die;
					}

					$uploadMaxSize = $params->get('upload_maxsize', 0) * 1024 * 1024;
					$uploadMaxFileSize = $mediaHelper->toBytes(ini_get('upload_max_filesize'));

					if (($file['error'] == 1) || ($uploadMaxSize > 0 && $file['size'] > $uploadMaxSize) || ($uploadMaxFileSize > 0 && $file['size'] > $uploadMaxFileSize))
					{
						$report['status'] = false;
						$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_MEDIA_LARGE');
						$error = true;
					}

					// File formats
					$accepted_file_formats = array(
						'image' => array('jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'),
						'video' => array('mp4', 'mov', 'wmv', 'avi', 'mpg', 'ogv', '3gp', '3g2'),
						'audio' => array('mp3', 'm4a', 'ogg', 'wav'),
						'attachment' => array('pdf', 'doc', 'docx', 'key', 'ppt', 'pptx', 'pps', 'ppsx', 'odt', 'xls', 'xlsx', 'zip', 'json'),
					);

					// Upload if no error found
					if (!$error)
					{
						$date = Factory::getDate();
						$file_ext = strtolower(File::getExt($file['name']));

						if (self::in_array($file_ext, $accepted_file_formats))
						{
							$media_type = self::array_search($file_ext, $accepted_file_formats);

							if ($media_type == 'image')
							{
								$mediaParams = ComponentHelper::getParams('com_media');
								$folder_root = $mediaParams->get('file_path', 'images') . '/';
							}
							elseif ($media_type == 'video')
							{
								$folder_root = 'media/videos/';
							}
							elseif ($media_type == 'audio')
							{
								$folder_root = 'media/audios/';
							}
							elseif ($media_type == 'attachment')
							{
								$folder_root = 'media/attachments/';
							}
							elseif ($media_type == 'fonts')
							{
								$folder_root = 'media/fonts/';
							}

							$report['type'] = $media_type;

							$folder = $folder_root . HTMLHelper::_('date', $date, 'Y') . '/' . HTMLHelper::_('date', $date, 'm') . '/' . HTMLHelper::_('date', $date, 'd');

							if ($dir != '')
							{
								$folder = ltrim($dir, '/');
							}

							if (!Folder::exists(JPATH_ROOT . '/' . $folder))
							{
								Folder::create(JPATH_ROOT . '/' . $folder, 0755);
							}

							if ($media_type == 'image')
							{
								if (!Folder::exists(JPATH_ROOT . '/' . $folder . '/_spmedia_thumbs'))
								{
									Folder::create(JPATH_ROOT . '/' . $folder . '/_spmedia_thumbs', 0755);
								}
							}

							$name = $file['name'];
							$path = $file['tmp_name'];
							// Do no override existing file

							$media_file = preg_replace('#\s+#', "-", File::makeSafe(basename(strtolower($name))));
							$i = 0;
							do
							{
								$base_name  = File::stripExt($media_file) . ($i ? "$i" : "");
								$ext        = File::getExt($media_file);
								$media_name = $base_name . '.' . $ext;
								$i++;
								$dest       = JPATH_ROOT . '/' . $folder . '/' . $media_name;
								$src        = $folder . '/'  . $media_name;
							} while (file_exists($dest));
							// End Do not override

							if (File::upload($path, $dest, false, true))
							{
								$media_attr = [];
								$thumb = '';

								if ($media_type == 'image')
								{
									list($imgWidth, $imgHeight) = getimagesize($dest);
									
									if (strtolower($ext) == 'svg')
									{
										$report['src'] = Uri::root(true) . '/' . $src;
									}
									else
									{
										$image = new SppagebuilderHelperImage($dest);
										$media_attr['full'] = ['height' => $image->height, 'width' => $image->width];

										if (($image->width > 300) || ($image->height > 225))
										{
											$thumbDestPath = dirname($dest) . '/_spmedia_thumbs';
											$created = $image->createThumb(array('300', '300'), $thumbDestPath, $base_name, $ext);

											if ($created == false)
											{
												$report['status'] = false;
												$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_FILE_NOT_SUPPORTED');
												$error = true;
												echo json_encode($report);
												die;
											}

											$report['src'] = Uri::root(true) . '/' . $folder . '/_spmedia_thumbs/' . $base_name . '.' . $ext;
											$thumb = $folder . '/_spmedia_thumbs/'  . $base_name . '.' . $ext;
											$humbdest = $thumbDestPath . '/' . $base_name . '.' . $ext;
											list($width, $height) = getimagesize($humbdest);
											$media_attr['thumbnail'] = ['height' => $height, 'width' => $width];
											$report['thumb'] = $thumb;
										}
										else
										{
											$report['src'] = Uri::root(true) . '/' . $src;
											$report['thumb'] = $src;
										}
										// Create placeholder for lazy load
										$this->create_media_placeholder($dest, $base_name, $ext);
									}
								}

								$insertid = $model->insertMedia($base_name, $src, json_encode($media_attr), $thumb, $media_type);
								$report['media_attr'] = $media_attr;
								$report['status'] = true;
								$report['title'] = $base_name;
								$report['id'] = $insertid;
								$report['path'] = $src;

								$layout_path = JPATH_ROOT . '/administrator/components/com_sppagebuilder/layouts';
								$format_layout = new FileLayout('media.format', $layout_path);
								$report['output'] = $format_layout->render(array('media' => $model->getMediaByID($insertid), 'innerHTML' => true));
							}
							else
							{
								$report['status'] = false;
								$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_UPLOAD_FAILED');
							}
						}
						else
						{
							$report['status'] = false;
							$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_FILE_NOT_SUPPORTED');
						}
					}
				}
			}
			else
			{
				$report['status'] = false;
				$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_UPLOAD_FAILED');
			}
		}
		else
		{
			$report['status'] = false;
			$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_UPLOAD_FAILED');
		}

		echo json_encode($report);
		die();
	}

	/**
	 * @since 2020
	 * Create light weight image placeholder for lazy load feature
	 */
	private function create_media_placeholder($dest, $base_name, $ext)
	{
		$placeholder_folder_path = JPATH_ROOT . '/media/com_sppagebuilder/placeholder';

		if (!Folder::exists($placeholder_folder_path))
		{
			Folder::create($placeholder_folder_path, 0755);
		}

		$image = new SppagebuilderHelperImage($dest);
		list($srcWidth, $srcHeight) = $image->getDimension();
		$width = 60;
		$height = $width / ($srcWidth / $srcHeight);
		$image->createThumb(array('60', $height), $placeholder_folder_path, $base_name, $ext, 20);
	}

	/**
	 * @since 2020
	 * Delete placeholder image if exists
	 */
	private function delete_image_placeholder($file_path)
	{
		$filename = basename($file_path);
		$src = JPATH_ROOT . '/media/com_sppagebuilder/placeholder' . '/' . $filename;
		if (File::exists($src))
		{
			File::delete($src);
		}
	}

	public function delete_media()
	{
		$app 		= Factory::getApplication('site');
		$input 		= $app->input;
		$user		= Factory::getUser();
		$model 		= $this->getModel();

		$data = $input->json->get('data', [], 'ARRAY');
		$response = ['status' => false, 'data' => 'Something went wrong!'];

		if(empty($data)) {
			$app->setHeader('status', 500, false);
			$app->sendHeaders();
			echo new JsonResponse($response);
			$app->close();
		}

		foreach ($data as $item)
			{
				if (!$this->removeMediaItem($item, $model, $user))
				{
					continue;
				}

				$response['data'] = 'Media item deleted!';
			}

		$response['status'] = true;

		$app->setHeader('status', 200, true);
		$app->sendHeaders();
		echo new JsonResponse($response);
		$app->close();
	}

	/**
	 * Remove a media item.
	 *
	 * @param 	stdClass 	$item	The media item object.
	 * @param 	object 		$model	The media model.
	 * @param 	object 		$user	The user class object.
	 *
	 * @return 	bool
	 * @since 	4.0.0
	 */
	private function removeMediaItem($item, $model, $user): bool
	{
		$mediaType = $item['type'];

		if ($mediaType === 'folder')
		{
			$path = Path::clean($item['path']);

			if (is_dir($path))
			{
				Folder::delete($path);

				return true;
			}

			return false;
		}
		elseif ($mediaType === 'local')
		{
			$src = JPATH_ROOT . '/' . Path::clean($item['path']);

			if (\file_exists($src))
			{
				$media = $model->getMediaByPath($item['path']);
				if (isset($media->thumb) && $media->thumb) {
					if (File::exists(JPATH_ROOT . '/' . $media->thumb)) {						
						File::delete(JPATH_ROOT . '/' . $media->thumb); // Delete thumb
					}
				}

				// Delete placeholder too
				$this->delete_image_placeholder($item['path']);
				// Remove Path.
				$removeMediaByPath = $model->removeMediaByPath($item['path']); 

				if (!File::delete($src) || !$removeMediaByPath) {
					return false;
				}				
				
				return true;
			}

			return false;
		}
		elseif ($mediaType === 'local+db')
		{

			$media = $model->getMediaByID($item['id']);
			$authorised = $user->authorise('core.edit', 'com_sppagebuilder') || ($user->authorise('core.edit.own', 'com_sppagebuilder') && ($media->created_by === $user->id));

			if (!$authorised)
			{
				return false;
			}

			if (!$model->removeMediaByID($item['id']))
			{
				return false;
			}

			$src = JPATH_ROOT . '/' . Path::clean(!empty($media->path) ? $media->path : '');

			if (!empty($media->thumb))
			{
				$thumbSrc = JPATH_ROOT . '/' . Path::clean($media->thumb);

				if (\file_exists($thumbSrc))
				{
					\unlink($thumbSrc);
				}
			}

			// Delete placeholder too
			$this->delete_image_placeholder($item['path']);

			if (\file_exists($src))
			{
				\unlink($src);

				return true;
			}

			return false;
		}

		return false;
	}

	// Delete File
	public function deleteMediaItem()
	{
		$model  = $this->getModel();
		$user = Factory::getUser();
		$input  = Factory::getApplication()->input;
		$m_type = $input->post->get('m_type', NULL, 'STRING');

		if ($m_type == 'path')
		{
			$report = array();
			$report['status'] = true;
			$path = htmlspecialchars($input->post->get('path', NULL, 'STRING'));
			$src = JPATH_ROOT . '/' . $path;

			if (File::exists($src))
			{
				if (!File::delete($src))
				{
					$report['status'] = false;
					$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_DELETE_FAILED');
					echo json_encode($report);
					die;
				}
			}
			else
			{
				$report['status'] = true;
			}

			echo json_encode($report);
		}
		else
		{
			$id = $input->post->get('id', NULL, 'INT');

			if (!is_numeric($id))
			{
				$report['status'] = false;
				$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_DELETE_FAILED');
				echo json_encode($report);
				die;
			}

			$media = $model->getMediaByID($id);

			$authorised = $user->authorise('core.edit', 'com_sppagebuilder') || ($user->authorise('core.edit.own', 'com_sppagebuilder') && ($media->created_by == $user->id));

			if ($authorised !== true)
			{
				$report['status'] = false;
				$report['output'] = Text::_('JERROR_ALERTNOAUTHOR');
				echo json_encode($report);
				die();
			}

			$src = JPATH_ROOT . '/' . $media->path;

			$report = array();
			$report['status'] = false;

			if (isset($media->thumb) && $media->thumb)
			{
				if (File::exists(JPATH_ROOT . '/' . $media->thumb))
				{
					File::delete(JPATH_ROOT . '/' . $media->thumb); // Delete thumb
				}
			}

			if (File::exists($src))
			{
				// Delete placeholder too
				$this->delete_image_placeholder($src);

				if (!File::delete($src))
				{
					$report['status'] = false;
					$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_DELETE_FAILED');
					echo json_encode($report);
					die;
				}
			}
			else
			{
				$report['status'] = true;
			}

			// Remove from database
			$media = $model->removeMediaByID($id);
			$report['status'] = true;

			echo json_encode($report);
		}

		die;
	}


	private static function in_array($needle, $haystack)
	{

		$it = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack));

		foreach ($it as $element)
		{
			if ($element == $needle)
			{
				return true;
			}
		}

		return false;
	}


	private static function array_search($needle, $haystack)
	{

		foreach ($haystack as $key => $value)
		{
			$current_key = $key;

			if ($needle === $value or (is_array($value) && self::array_search($needle, $value) !== false))
			{
				return $current_key;
			}
		}
		return false;
	}

	// Create folder
	public function create_folder()
	{
		$input  = Factory::getApplication()->input;
		$folder = $input->post->get('folder', '', 'STRING');

		$dirname = dirname($folder);
		$basename = OutputFilter::stringURLSafe(basename($folder));
		$folder = $dirname . '/' . $basename;

		$report = array();
		$report['status'] = false;
		$fullname = JPATH_ROOT . $folder;

		if (!Folder::exists(JPATH_ROOT . $folder))
		{
			if (Folder::create(JPATH_ROOT . $folder, 0755))
			{
				$report['status'] = true;

				$folder_info['name'] = basename($folder);
				$folder_info['relname'] = $folder;
				$folder_info['fullname'] = $fullname;
				$report['output'] = $folder_info;
			}
			else
			{
				$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_FOLDER_CREATION_FAILED');
			}
		}
		else
		{
			$report['output'] = Text::_('COM_SPPAGEBUILDER_MEDIA_MANAGER_FOLDER_EXISTS');
		}

		echo json_encode($report);
		die;
	}

	public function delete_folder()
	{
		$input  = Factory::getApplication()->input;
		$folder = $input->post->get('folder', '', 'STRING');
		$deleteItem = $input->post->get('deleteItem', '', 'STRING');
		$model  = $this->getModel();
		$dirname = dirname($folder);
		$basename = OutputFilter::stringURLSafe(basename($folder));
		$folder = $dirname . '/' . $basename;
		$report = array();
		$report['status'] = false;
		$fullname = JPATH_ROOT . $folder;

		if (Folder::exists(JPATH_ROOT . $folder))
		{
			if ($deleteItem === 'multiple')
			{
				$mediaDelete = $model->removeMediaByPath(substr($folder, 1) . '/');
			}
			else
			{
				$mediaDelete = true;
			}

			if ($mediaDelete === true)
			{
				if (Folder::delete(JPATH_ROOT . $folder))
				{
					$report['status'] = true;
					$folder_info['name'] = basename($folder);
					$folder_info['relname'] = $folder;
					$report['output'] = $folder_info;
				}
				else
				{
					$report['output'] = "FOLDER DELETE FAILED";
				}
			}
			else
			{
				$report['output'] = 'MEDIA FILES COULD NOT BE DELETED';
			}
		}
		else
		{
			$report['output'] = "FOLDER_DOES_NOT_EXISTS";
		}

		echo json_encode($report);
		die;
	}

	public function rename_folder()
	{
		$input  = Factory::getApplication()->input;
		$model  = $this->getModel();
		$currentfolder = $input->post->get('currentfolder', '', 'STRING');
		$newfolder = $input->post->get('newfolder', '', 'STRING');
		$renameItem = $input->post->get('renameItem', '', 'STRING');
		$dirname = dirname($currentfolder);
		$currentbasename = OutputFilter::stringURLSafe(basename($currentfolder));
		$newbasename = OutputFilter::stringURLSafe(basename($newfolder));
		$src = $dirname . '/' . $currentbasename;
		$dest = $dirname . '/' . $newbasename;
		$totalfiles = array();

		if (Folder::exists(JPATH_ROOT . $currentfolder))
		{
			if ($renameItem === 'multiple')
			{
				$mediaRename = $model->editMediaPathById(substr($src, 1) . '/', substr($dest, 1) . '/');
			}
			else
			{
				$mediaRename = true;
			}

			if ($mediaRename === true)
			{
				if (Folder::move(JPATH_ROOT . $src, JPATH_ROOT . $dest, $path = '', $use_streams = false))
				{
					$report['status'] = true;
					$folder_info['name'] = basename($dest);
					$folder_info['relname'] = $dest;
					$folder_info['fullname'] = JPATH_ROOT . $dest;
					$report['output'] = $folder_info;
				}
				else
				{
					$report['output'] = "FOLDER RENAME FAILED";
				}
			}
			else
			{
				$report['output'] = $mediaRename;
				//'MEDIA FILES COULD NOT BE RENAMED';
			}
		}
		else
		{
			$report['output'] = "FOLDER_DOES_NOT_EXISTS";
		}
		echo json_encode($report);
		die;
	}
}