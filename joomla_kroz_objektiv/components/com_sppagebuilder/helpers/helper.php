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
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Plugin\PluginHelper;

require_once JPATH_ROOT . '/components/com_sppagebuilder/builder/classes/base.php';

class SppagebuilderHelperSite
{
    /**
     * Predict the column's fill style if it is fit in a single line
     * or multiple line.
     *
     * @param     array         $columns     The columns array.
     *
     * @return     stdClass     The fit value after prediction.
     * @since     4.0.0
     */
    private static function predictColumnFillStyle(array $columns): stdClass
    {
        $fitObject = (object) ['xxl' => false, 'xl' => false, 'lg' => false, 'md' => false, 'sm' => false, 'xs' => false];

        foreach (['xxl', 'xl', 'lg', 'md', 'sm', 'xs'] as $key)
        {
            $total = 0;

            foreach ($columns as $column)
            {
                $width = self::getColumnWidth($column);
                $total += (float) $width->$key;
            }

            if ($total <= 100)
            {
                $fitObject->$key = true;
            }
        }

        return $fitObject;
    }

    /**
     * Generate the column widths from the columns class_name property.
     *
     * @param     stdClass     $column     The column object.
     *
     * @return     stdClass                 The generated width object for multiple device.
     * @since     4.0.0
     */
    private static function getColumnWidth(stdClass $column): stdClass
    {
        $width = (object) ['xxl' => '100%', 'xl' => '100%', 'lg' => '100%', 'md' => '100%', 'sm' => '100%', 'xs' => '100%'];
        $size = (int) \substr($column->class_name, 7);
        $value = self::calculateColumnPercent($size);

        $width->xxl = $value;
        $width->xl = $value;

        if (!empty($column->settings->sm_col))
        {
            $smSize = (int) \substr($column->settings->sm_col, 7);
            $width->md = self::calculateColumnPercent($smSize);
            $width->lg = self::calculateColumnPercent($smSize);
        }

        if (!empty($column->settings->xs_col))
        {
            $xsSize = (int) \substr($column->settings->xs_col, 7);
            $width->xs = self::calculateColumnPercent($xsSize);
            $width->sm = self::calculateColumnPercent($xsSize);
        }

        return $width;
    }

    /**
     * Calculate the column percentage from the column size.
     *
     * @param     int     $size     The size value ranged from 1 to 12.
     *
     * @return    string            The percentage value w.r.t the the size.
     * @since     4.0.0
     */
    private static function calculateColumnPercent(int $size): string
    {
        return ((100 / 12) * (int) $size) . '%';
    }

    // Generate unique id
    public static function nanoid(int $size = 21) : string
    {
        $urlAlphabet = "ModuleSymbhasOwnPr-0123456789ABCDEFGHNRVfgctiUvz_KqYTJkLxpZXIjQW";
        $id = "";
        $i = $size;

        while ($i--) {
            $id .= $urlAlphabet[rand(0,63) | 0];
        }

        return $id;
    }

    private static function parseRow(array &$rows, \stdClass &$row)
    {
        if (isset($row->settings))
        {
            $row->settings = self::shiftResponsiveSettings($row->settings);
        }

        if (!empty($row->columns))
        {
            if (!isset($row->settings->fit_columns))
            {
                $row->settings->fit_columns = self::predictColumnFillStyle($row->columns);
            }

            foreach ($row->columns as $i => &$column)
            {
                /** Predict the width from the column class name for the old layouts. */
                if (!isset($column->width))
                {
                    $width = self::getColumnWidth($column);
                    $column->width = $width;
                }

                if (isset($column->settings))
                {
                    $column
                        ->settings = self::shiftResponsiveSettings($column->settings);
                }

                if (!empty($column->addons))
                {
                    foreach ($column->addons as $j => &$addon)
                    {
                        if (isset($addon->settings))
                        {
                            
                            $addon
                                ->settings = self::shiftResponsiveSettings($addon->settings);
                        }

                        /** Migrate the slideshow items. */
                        if (isset($addon->name) && $addon->name === 'js_slideshow')
                        {
                            if (!empty($addon->settings->slideshow_items))
                            {
                                foreach ($addon->settings->slideshow_items as $x => &$slideshowItem)
                                {
                                    $slideshowItem = self::shiftResponsiveSettings($slideshowItem);

                                    if (!empty($slideshowItem->slideshow_inner_items))
                                    {
                                        foreach ($slideshowItem->slideshow_inner_items as $y => &$innerItem)
                                        {
                                            $innerItem = self::shiftResponsiveSettings($innerItem);
                                        }

                                        unset($innerItem);
                                    }
                                }

                                unset($slideshowItem);
                            }
                        }

                        /** Migrate the responsive settings for the repeatable items. */
                        if (isset($addon->name) && \in_array($addon->name, ['accordion', 'tab']))
                        {
                            $repeatableKey = 'sp_' . $addon->name . '_item';

                            if (!empty($addon->settings->$repeatableKey))
                            {
                                foreach ($addon->settings->$repeatableKey as &$itemSetting)
                                {
                                    $itemSetting = self::shiftResponsiveSettings($itemSetting);
                                }

                                unset($itemSetting);
                            }
                        }

                        if (isset($addon->name) && \in_array($addon->name, ['accordion', 'tab']))
                        {
                            list($outerRows, $_addon) = self::migrateDeepAddon($addon, 'sp_' . $addon->name . '_item', $row, $column);
                            $addon = $_addon;
                            array_push($rows, ...$outerRows);
                        }

                        if (isset($addon->name) && $addon->name === 'table_advanced')
                        {
                            $nodeId = self::generateUUID();

                            if (isset($addon->settings->sp_table_advanced_item))
                            {
                                foreach ($addon->settings->sp_table_advanced_item as $th => $thead)
                                {
                                    if (isset($thead->content) && !\is_array($thead->content))
                                    {
                                        $thead = ['id' => $nodeId++, 'name' => 'text_block', 'visibility' => true, 'reference_id' => $addon->id, 'settings' => ['text' => $thead->content]];
                                        $thead = \json_decode(\json_encode($thead));
                                        $addon
                                            ->settings
                                            ->sp_table_advanced_item[$th]
                                            ->content = [];
                                        $addon
                                            ->settings
                                            ->sp_table_advanced_item[$th]
                                            ->content[] = $thead;
                                    }
                                    elseif (isset($thead->content) && \is_array($thead->content))
                                    {
                                        $contents = [];

                                        foreach ($thead->content as $content)
                                        {
                                            $content->reference_id = $addon->id;
                                            $contents[] = $content;
                                        }

                                        $addon
                                            ->settings
                                            ->sp_table_advanced_item[$th]
                                            ->content = $contents;
                                    }
                                }
                            }

                            foreach ($addon->settings->table_advanced_item as $r => $tRow)
                            {
                                if (isset($tRow->table_advanced_item))
                                {
                                    foreach ($tRow->table_advanced_item as $c => $tCell)
                                    {
                                        if (isset($tCell->content) && !\is_array($tCell->content))
                                        {
                                            $td = ['id' => $nodeId++, 'name' => 'text_block', 'visibility' => true, 'reference_id' => $addon->id, 'settings' => ['text' => $tCell->content]];
                                            $td = \json_decode(\json_encode($td));

                                            $addon
                                                ->settings
                                                ->table_advanced_item[$r]
                                                ->table_advanced_item[$c]
                                                ->content = [];
                                            $addon
                                                ->settings
                                                ->table_advanced_item[$r]
                                                ->table_advanced_item[$c]
                                                ->content[] = $td;
                                        }
                                        elseif (isset($tCell->content) && \is_array($tCell->content))
                                        {
                                            $contents = [];

                                            foreach ($tCell->content as &$content)
                                            {
                                                $content->reference_id = $addon->id;
                                                $contents[] = $content;
                                            }

                                            $addon
                                                ->settings
                                                ->table_advanced_item[$r]
                                                ->table_advanced_item[$c]
                                                ->content = $contents;

                                            unset($content);
                                        }
                                    }
                                }
                            }
                        }

                        if (isset($addon->type) && $addon->type === 'inner_row')
                        {
                            $addon->id = self::nanoid();

                            $nestedRowAddon = new \stdClass;
                            $nestedRowAddon->type = 'nested_row';
                            $nestedRowAddon->name = 'row';
                            $nestedRowAddon->id = $addon->id;

                            $addon->parent = new \stdClass;
                            $addon->parent->rowId = $row->id;
                            $addon->parent->columnId = $column->id;
                            
                            $rows[] = $addon;
                            $addon = $nestedRowAddon;
                        }
                    }

                    unset($addon);
                }
            }

            unset($column);
        }
    }

    /**
     * sanitize import json for making the old data valid for the data structure.
     *
     * @param    string    $text    The json text of the page builder data.
     *
     * @return   string    The sanitized text.
     * 
     * @since    4.0.0
     */
    public static function sanitizeImportJSON(string $text): string
    {
        $rows = json_decode($text);

        if (!empty($rows))
        {
            foreach ($rows as $key => &$row)
            {
                self::parseRow($rows, $row);
            }

            unset($row);
        }
        else
        {
            return $text;
        }

        return json_encode($rows);
    }

    /**
     * sanitize contents for making the old data valid for the data structure.
     *
     * @param    string    $text    The json text of the page builder data.
     *
     * @return   string    The sanitized text.
     * 
     * @since    4.0.0
     */
    public static function sanitize(string $text): string
    {
        $rows = json_decode($text);

        if (!empty($rows))
        {
            foreach ($rows as $key => &$row)
            {
                if (isset($rows[$key]->settings))
                {
                    $rows[$key]->settings = self::shiftResponsiveSettings($row->settings);
                }

                if (!empty($row->columns))
                {
                    if (!isset($row->settings->fit_columns))
                    {
                        $row->settings->fit_columns = self::predictColumnFillStyle($row->columns);
                    }

                    foreach ($row->columns as $i => &$column)
                    {
                        /** Predict the width from the column class name for the old layouts. */
                        if (!isset($column->width))
                        {
                            $width = self::getColumnWidth($column);
                            $column->width = $width;
                        }

                        if (isset($rows[$key]->columns[$i]->settings))
                        {
                            $rows[$key]
                                ->columns[$i]
                                ->settings = self::shiftResponsiveSettings($rows[$key]->columns[$i]->settings);
                        }

                        if (!empty($column->addons))
                        {
                            foreach ($column->addons as $j => &$addon)
                            {
                                if (isset($rows[$key]->columns[$i]->addons[$j]->settings))
                                {
                                    $rows[$key]
                                        ->columns[$i]
                                        ->addons[$j]
                                        ->settings = self::shiftResponsiveSettings($rows[$key]->columns[$i]->addons[$j]->settings);

                                        if (isset($addon->name) && $addon->name === 'image_layouts')
                                        {
                                            $addonSettings = $addon->settings;

                                            $isBtnTextExist = property_exists($addonSettings, 'btn_text');
                                            $isButtonTextExist = property_exists($addonSettings, 'button_text');

                                            if (!$isButtonTextExist && $isBtnTextExist)
                                            {
                                                $addonSettings->button_text = $addonSettings->btn_text;
                                            }

                                            $isBtnTypeExist = property_exists($addonSettings, 'btn_type');
                                            $isButtonTypeExist = property_exists($addonSettings, 'button_type');

                                            if (!$isButtonTypeExist && $isBtnTypeExist)
                                            {
                                                $addonSettings->button_type = $addonSettings->btn_type;
                                            }

                                            $isBtnShapeExist = property_exists($addonSettings, 'btn_shape');
                                            $isButtonShapeExist = property_exists($addonSettings, 'button_shape');

                                            if (!$isButtonShapeExist && $isBtnShapeExist)
                                            {
                                                $addonSettings->button_shape = $addonSettings->btn_shape;
                                            }

                                            $isBtnSizeExist = property_exists($addonSettings, 'btn_size');
                                            $isButtonSizeExist = property_exists($addonSettings, 'button_size');

                                            if (!$isButtonSizeExist && $isBtnSizeExist)
                                            {
                                                $addonSettings->button_size = $addonSettings->btn_size;
                                            }

                                            $isBtnColorExist = property_exists($addonSettings, 'btn_color');
                                            $isButtonColorExist = property_exists($addonSettings, 'button_color');

                                            if (!$isButtonColorExist && $isBtnColorExist)
                                            {
                                                $addonSettings->button_color = $addonSettings->btn_color;
                                            }

                                            $isBtnColorHoverExist = property_exists($addonSettings, 'btn_color_hover');
                                            $isButtonColorHoverExist = property_exists($addonSettings, 'button_color_hover');

                                            if (!$isButtonColorHoverExist && $isBtnColorHoverExist)
                                            {
                                                $addonSettings->button_color_hover = $addonSettings->btn_color_hover;
                                            }

                                            $isBtnAppearanceExist = property_exists($addonSettings, 'btn_appearance');
                                            $isButtonAppearanceExist = property_exists($addonSettings, 'button_appearance');

                                            if (!$isButtonAppearanceExist && $isBtnAppearanceExist)
                                            {
                                                $addonSettings->button_appearance = $addonSettings->btn_appearance;
                                            }

                                            $isBtnBackgroundColorExist = property_exists($addonSettings, 'btn_background_color');
                                            $isButtonBackgroundColorExist = property_exists($addonSettings, 'button_background_color');

                                            if (!$isButtonBackgroundColorExist && $isBtnBackgroundColorExist)
                                            {
                                                $addonSettings->button_background_color = $addonSettings->btn_background_color;
                                            }

                                            $isBtnBackgroundColorHoverExist = property_exists($addonSettings, 'btn_background_color_hover');
                                            $isButtonBackgroundColorHoverExist = property_exists($addonSettings, 'button_background_color_hover');

                                            if (!$isButtonBackgroundColorHoverExist && $isBtnBackgroundColorHoverExist)
                                            {
                                                $addonSettings->button_background_color_hover = $addonSettings->btn_background_color_hover;
                                            }

                                            $isBtnBackgroundGradientExist = property_exists($addonSettings, 'btn_background_gradient');
                                            $isButtonBackgroundGradientExist = property_exists($addonSettings, 'button_background_gradient');

                                            if (!$isButtonBackgroundGradientExist && $isBtnBackgroundGradientExist)
                                            {
                                                $addonSettings->button_background_gradient = $addonSettings->btn_background_gradient;
                                            }

                                            $isBtnBackgroundGradientHoverExist = property_exists($addonSettings, 'btn_background_gradient_hover');
                                            $isButtonBackgroundGradientHoverExist = property_exists($addonSettings, 'button_background_gradient_hover');

                                            if (!$isButtonBackgroundGradientHoverExist && $isBtnBackgroundGradientHoverExist)
                                            {
                                                $addonSettings->button_background_gradient_hover = $addonSettings->btn_background_gradient_hover;
                                            }
                                        }
                                }

                                /** Migrate the slideshow items. */
                                if (isset($addon->name) && $addon->name === 'js_slideshow')
                                {
                                    if (!empty($addon->settings->slideshow_items))
                                    {
                                        foreach ($addon->settings->slideshow_items as $x => &$slideshowItem)
                                        {
                                            $slideshowItem = self::shiftResponsiveSettings($slideshowItem);

                                            if (!empty($slideshowItem->slideshow_inner_items))
                                            {
                                                foreach ($slideshowItem->slideshow_inner_items as $y => &$innerItem)
                                                {
                                                    $innerItem = self::shiftResponsiveSettings($innerItem);
                                                }

                                                unset($innerItem);
                                            }
                                        }

                                        unset($slideshowItem);
                                    }
                                }

                                /** Migrate the responsive settings for the repeatable items. */
                                if (isset($addon->name) && \in_array($addon->name, ['accordion', 'tab']))
                                {
                                    $repeatableKey = 'sp_' . $addon->name . '_item';

                                    if (!empty($addon->settings->$repeatableKey))
                                    {
                                        foreach ($rows[$key]->columns[$i]->addons[$j]->settings->$repeatableKey as &$itemSetting)
                                        {
                                            $itemSetting = self::shiftResponsiveSettings($itemSetting);
                                        }

                                        unset($itemSetting);
                                    }
                                }

                                if (isset($addon->name) && \in_array($addon->name, ['accordion', 'tab']))
                                {
                                    list($outerRows, $addon) = self::migrateDeepAddon($addon, 'sp_' . $addon->name . '_item', $row, $column);
                                    $rows[$key]->columns[$i]->addons[$j] = $addon;
                                    array_push($rows, ...$outerRows);
                                }

                                if (isset($addon->name) && $addon->name === 'table_advanced')
                                {
                                    $nodeId = self::generateUUID();

                                    if (isset($addon->settings->sp_table_advanced_item))
                                    {
                                        foreach ($addon->settings->sp_table_advanced_item as $th => $thead)
                                        {
                                            if (isset($thead->content) && !\is_array($thead->content))
                                            {
                                                $thead = ['id' => $nodeId++, 'name' => 'text_block', 'visibility' => true, 'reference_id' => $addon->id, 'settings' => ['text' => $thead->content]];
                                                $thead = \json_decode(\json_encode($thead));
                                                $rows[$key]
                                                    ->columns[$i]
                                                    ->addons[$j]
                                                    ->settings
                                                    ->sp_table_advanced_item[$th]
                                                    ->content = [];
                                                $rows[$key]
                                                    ->columns[$i]
                                                    ->addons[$j]
                                                    ->settings
                                                    ->sp_table_advanced_item[$th]
                                                    ->content[] = $thead;
                                            }
                                            elseif (isset($thead->content) && \is_array($thead->content))
                                            {
                                                $contents = [];

                                                foreach ($thead->content as $content)
                                                {
                                                    $content->reference_id = $addon->id;
                                                    $contents[] = $content;
                                                }

                                                $rows[$key]
                                                    ->columns[$i]
                                                    ->addons[$j]
                                                    ->settings
                                                    ->sp_table_advanced_item[$th]
                                                    ->content = $contents;
                                            }
                                        }
                                    }

                                    foreach ($addon->settings->table_advanced_item as $r => $tRow)
                                    {
                                        if (isset($tRow->table_advanced_item))
                                        {
                                            foreach ($tRow->table_advanced_item as $c => $tCell)
                                            {
                                                if (isset($tCell->content) && !\is_array($tCell->content))
                                                {
                                                    $td = ['id' => $nodeId++, 'name' => 'text_block', 'visibility' => true, 'reference_id' => $addon->id, 'settings' => ['text' => $tCell->content]];
                                                    $td = \json_decode(\json_encode($td));

                                                    $rows[$key]
                                                        ->columns[$i]
                                                        ->addons[$j]
                                                        ->settings
                                                        ->table_advanced_item[$r]
                                                        ->table_advanced_item[$c]
                                                        ->content = [];
                                                    $rows[$key]
                                                        ->columns[$i]
                                                        ->addons[$j]
                                                        ->settings
                                                        ->table_advanced_item[$r]
                                                        ->table_advanced_item[$c]
                                                        ->content[] = $td;
                                                }
                                                elseif (isset($tCell->content) && \is_array($tCell->content))
                                                {
                                                    $contents = [];

                                                    foreach ($tCell->content as &$content)
                                                    {
                                                        $content->reference_id = $addon->id;
                                                        $contents[] = $content;
                                                    }

                                                    $rows[$key]
                                                        ->columns[$i]
                                                        ->addons[$j]
                                                        ->settings
                                                        ->table_advanced_item[$r]
                                                        ->table_advanced_item[$c]
                                                        ->content = $contents;

                                                    unset($content);
                                                }
                                            }
                                        }
                                    }
                                }

                                if (isset($addon->type) && $addon->type === 'inner_row')
                                {
                                    $nestedRowAddon = new \stdClass;
                                    $nestedRowAddon->type = 'nested_row';
                                    $nestedRowAddon->name = 'row';
                                    $nestedRowAddon->id = $addon->id;
                                    $addon->parent = new \stdClass;
                                    $addon->parent->rowId = $row->id;
                                    $addon->parent->columnId = $column->id;

                                    unset($addon->type);
                                    $rows[] = $addon;
                                    $rows[$key]->columns[$i]->addons[$j] = $nestedRowAddon;
                                }
                            }

                            unset($addon);
                        }
                    }

                    unset($column);
                }
            }

            unset($row);
        }
        else
        {
            return $text;
        }

        return json_encode($rows);
    }

    /**
     * Migrate the accordion addon to the current structure.
     *
     * @param     \stdClass     $addon    The accordion addon object.
     *
     * @return     array
     * @since     4.0.0
     */
    private static function migrateDeepAddon(\stdClass $addon, $key, $row, $column): array
    {
        $addon = json_decode(json_encode($addon));
        $addonCollection = [];
        $outerRows = [];
        
        if (!isset($addon->parent) || (isset($addon->parent) && !$addon->parent))
        {
            $addon->id = self::nanoid();
        }

        if (isset($addon->settings->$key))
        {
            foreach ($addon->settings->$key as $itemIndex => $item)
            {
                $addonCollection = [];

                if (isset($item->content) && \is_array($item->content))
                {
                    foreach ($item->content as $deepAddon)
                    {
                        if (isset($deepAddon->type) && $deepAddon->type === 'nested_row')
                        {
                            continue;
                        }

                        $addonCollection[] = $deepAddon;
                    }

                    if (\count($addonCollection) > 0)
                    {
                        $_parent = ['rowId' => $row->id, 'columnId' => $column->id];
                        $_parent = (object) $_parent;
                        $row = self::createRow('12', $addonCollection, $_parent);
                        $row->parent_addon = $addon->id;

                        $outerRows[] = $row;
                        $nestedRow = ['type' => 'nested_row', 'id' => $row->id, 'name' => 'row'];
                        $nestedRow = (object) $nestedRow;
                        $addon->settings->$key[$itemIndex]->content = [];
                        $addon->settings->$key[$itemIndex]->content[] = $nestedRow;
                    }
                }
                else if (isset($item->content) && \is_string($item->content))
                {
                    $textAddon = ['id' => self::nanoid(), 'name' => 'text_block', 'settings' => ['text' => $item->content]];
                    $addonCollection[] = $textAddon;

                    $_parent = ['rowId' => $row->id, 'columnId' => $column->id];
                    $_parent = (object) $_parent;
                    $row = self::createRow('12', $addonCollection, $_parent);
                    $row->parent_addon = $addon->id;

                    $outerRows[] = $row;
                    $nestedRow = ['type' => 'nested_row', 'id' => $row->id, 'name' => 'row'];
                    $nestedRow = (object) $nestedRow;
                    $addon->settings->$key[$itemIndex]->content = [];
                    $addon->settings->$key[$itemIndex]->content[] = $nestedRow;
                }
            }
        }
        
        return [$outerRows, $addon];
    }

    /**
     * Create Row function
     *
     * @param string $layout Default layout size
     * @param array $addons Addons
     * @param mixed $parent Parent row.
     *   
     * @return object
     * 
     * @since 4.0.0
     */
    public static function createRow(string $layout = '12', array $addons = [], $parent = null)
    {
        $rowId = self::nanoid();
        $layouts = explode('+', $layout);
        $globalColumn = SpPgaeBuilderBase::getColumnGlobalSettings();
        $globalRow = SpPgaeBuilderBase::getRowGlobalSettings();

        $rowDefaultValues = SpPgaeBuilderBase::getSettingsDefaultValue($globalRow['attr'])['default'];
        $columnDefaultValues = SpPgaeBuilderBase::getSettingsDefaultValue($globalColumn['attr'])['default'];

        $rowDefaultValues = json_decode(json_encode($rowDefaultValues));
        $columnDefaultValues = json_decode(json_encode($columnDefaultValues));

        $columns = array_map(function ($col) use ($columnDefaultValues, $addons)
        {
            $width = (float) ((100 / (12 / (int) $col))) . '%';
            $widthObject = ['xxl' => $width, 'xl' => $width, 'lg' => $width, 'md' => $width, 'sm' => '100%', 'xs' => '100%'];
            $widthObject = (object) $widthObject;

            $columnObject = [
                'id' => self::nanoid(),
                'class_name' => 'row-column',
                'visibility' => true,
                'settings' => $columnDefaultValues,
                'addons' => $addons,
                'width' => $widthObject,
            ];

            return (object) $columnObject;
        }, $layouts);

        $rowDefaultValues->padding = '5px 0px 5px 0px';
        $rowDefaultValues->margin = '0px 0px 0px 0px';

        $rowObject = [
            'id' => $rowId,
            'visibility' => true,
            'collapse' => false,
            'settings' => $rowDefaultValues,
            'layout' => $layout,
            'columns' => $columns,
            'parent' => $parent ? $parent : false,
        ];

        return (object) $rowObject;
    }

    /**
     * Generate a unique ID by using microtime.
     *
     * @return     integer
     * @since     4.0.0
     */
    public static function generateUUID(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * Shift responsive device settings for with the new device structure.
     *
     * @param     \stdClass     $settings    The settings value.
     *
     * @return     \stdClass | null
     * @since     4.0.0
     */
    public static function shiftResponsiveSettings($settings)
    {
        if (!empty($settings))
        {
            foreach ($settings as $key => $setting)
            {
                if (\is_object($setting) && isset($setting->md) && !isset($setting->xl))
                {
                    $tmp = ['xxl' => '', 'xl' => '', 'lg' => '', 'md' => '', 'sm' => '', 'xs' => ''];
                    $tmp = (object) $tmp;

                    if (isset($setting->md))
                    {
                        $tmp->xxl = $setting->md;
                        $tmp->xl = $setting->md;
                    }

                    if (isset($setting->sm))
                    {
                        $tmp->lg = $setting->sm;
                        $tmp->md = $setting->sm;
                    }

                    if (isset($setting->xs))
                    {
                        $tmp->sm = $setting->xs;
                        $tmp->xs = $setting->xs;
                    }

                    if (isset($setting->unit))
                    {
                        $tmp->unit = $setting->unit;
                    }

                    $settings->$key = $tmp;
                }
            }
        }

        return $settings;
    }

    /**
     * Remove sp_ from the addon name
     *
     * @return    void
     * @since    4.0.0
     */
    public static function sanitize_addon_name($addon_name)
    {
        $from = '/' . preg_quote('sp_', '/') . '/';
        return preg_replace($from, '', $addon_name, 1);
    }

    /**
     * Load Language File
     *
     * @param boolean $forceLoad
     * @return void
     */
    public static function loadLanguage($forceLoad = false)
    {
        $lang = Factory::getLanguage();

        $app = Factory::getApplication();
        $template = $app->getTemplate();

        $com_option = $app->input->get('option', '', 'STR');
        $com_view = $app->input->get('view', '', 'STR');
        $com_id = $app->input->get('id', 0, 'INT');

        if (($com_option == 'com_sppagebuilder' && $com_view == 'form' && $com_id) || $forceLoad)
        {
            $lang->load('com_sppagebuilder', JPATH_ADMINISTRATOR, null, true);
        }

        // Load template language file
        $lang->load('tpl_' . $template, JPATH_SITE, null, true);

        self::setPluginsAddonsLanguage();

        require_once JPATH_ROOT . '/administrator/components/com_sppagebuilder/helpers/language.php';
    }

    /**
     * Load Plugin addons language files.
     *
     * @return void
     */
	private static function setPluginsAddonsLanguage()
	{
		$path = JPATH_PLUGINS . '/sppagebuilder';
		if (!Folder::exists($path)) return;

		$plugins = Folder::folders($path);
		if (!count((array) $plugins)) return;

		foreach ($plugins as $plugin)
		{
			if (PluginHelper::isEnabled('sppagebuilder', $plugin))
			{
				$lang = Factory::getLanguage();
				$lang->load('plg_' . $plugin, JPATH_ADMINISTRATOR, null, true);
			}
		}
	}

    /**
     * Convert Padding Margin Value.
     *
     * @param string $main_value CSS value
     * @param string $type  CSS property
     * 
     * @return string
     * 
     * @since 4.0.0
     */
    public static function getPaddingMargin($main_value, $type) : string
    {
        $css = '';
        $pos = array('top', 'right', 'bottom', 'left');
        if (is_string($main_value) && trim($main_value) != "")
        {
            $values = explode(' ', $main_value);
            foreach ($values as $key => $value)
            {
                if (trim($value) != "")
                {
                    $css .= $type . '-' . $pos[$key] . ': ' . $value . ';';
                }
            }
        }

        return $css;
    }

    public static function getSvgShapes()
    {
        $shape_path = JPATH_ROOT . '/components/com_sppagebuilder/assets/shapes';
        $shapes = Folder::files($shape_path, '.svg');

        $shapeArray = array();

        if (count((array) $shapes))
        {
            foreach ($shapes as $shape)
            {
                $shapeArray[str_replace('.svg', '', $shape)] = base64_encode(file_get_contents($shape_path . '/' . $shape));
            }
        }

        return $shapeArray;
    }

    public static function getSvgShapeCode($shapeName, $invert)
    {
        if ($invert)
        {
            $shape_path = JPATH_ROOT . '/components/com_sppagebuilder/assets/shapes/' . $shapeName . '-invert.svg';
        }
        else
        {
            $shape_path = JPATH_ROOT . '/components/com_sppagebuilder/assets/shapes/' . $shapeName . '.svg';
        }

        $shapeCode = '';

        if (file_exists($shape_path))
        {
            $shapeCode = file_get_contents($shape_path);
        }

        return $shapeCode;
    }

    // Convert json code to plain text
    public static function getPrettyText($sections)
    {
        if (!class_exists('AddonParser'))
        {
            require_once JPATH_ROOT . '/components/com_sppagebuilder/parser/addon-parser.php';
        }
        if (!class_exists('SpPageBuilderAddonHelper'))
        {
            require_once JPATH_ROOT . '/components/com_sppagebuilder/builder/classes/addon.php';
        }

        $sections = SpPageBuilderAddonHelper::__($sections);
        $content = json_decode($sections);
        $htmlContent = AddonParser::viewAddons($content);
        $htmlContent = str_replace('><', '> <', $htmlContent);

        return trim(strip_tags($htmlContent));
    }

    public static function addScript($script, $client = 'site', $version = true)
    {
        $doc = Factory::getDocument();

        $script_url = Uri::base(true) . ($client == 'admin' ? '/administrator' : '') . '/components/com_sppagebuilder/assets/js/' . $script;

        if ($version)
        {
            $script_url .= '?' . self::getVersion(true);
        }

        $doc->addScript($script_url);
    }

    public static function addStylesheet($stylesheet, $client = 'site', $version = true)
    {
        $doc = Factory::getDocument();

        $stylesheet_url = Uri::base(true) . ($client == 'admin' ? '/administrator' : '') . '/components/com_sppagebuilder/assets/css/' . $stylesheet;

        if ($version)
        {
            $stylesheet_url .= '?' . self::getVersion(true);
        }

        $doc->addStylesheet($stylesheet_url);
    }

    public static function getVersion($md5 = false)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('e.manifest_cache')
            ->select($db->quoteName('e.manifest_cache'))
            ->from($db->quoteName('#__extensions', 'e'))
            ->where($db->quoteName('e.element') . ' = ' . $db->quote('com_sppagebuilder'));

        $db->setQuery($query);
        $manifest_cache = json_decode($db->loadResult());

        if (isset($manifest_cache->version) && $manifest_cache->version)
        {

            if ($md5)
            {
                return md5($manifest_cache->version);
            }

            return $manifest_cache->version;
        }

        return '1.0';
    }

    /**
     * Load Assets form database table.
     *
     * @return void
     */
    public static function loadAssets()
    {
        $doc = Factory::getDocument();
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('a.name', 'a.css_path')))
            ->from($db->quoteName('#__sppagebuilder_assets', 'a'))
            ->where($db->quoteName('a.published') . ' = 1');

        $db->setQuery($query);
        $assets = $db->loadObjectList();

        if (!empty($assets))
        {
            foreach ($assets as $asset)
            {
                $asset_url = Uri::base(true) . '/' . $asset->css_path . '?' . self::getVersion(true);
                $doc->addStylesheet($asset_url);
            }
        }
    }

    /**
     * Get the current template name form database.
     *
     * @return void
     */
    public static function getTemplateName()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName(['template']))
            ->from($db->quoteName('#__template_styles'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('home') . ' = 1');
        $db->setQuery($query);

        return $db->loadObject()->template;
    }
}
