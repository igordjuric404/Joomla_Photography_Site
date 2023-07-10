<?php

/**
 * @package SP Page Builder
 * @author JoomShaper http:   //www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2023 JoomShaper
 * @license http:   //www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
//no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

SpAddonsConfig::addonConfig(
    [
        'type'       => 'general',
        'addon_name' => 'button',
        'title'      => Text::_('COM_SPPAGEBUILDER_ADDON_BUTTON'),
        'desc'       => Text::_('COM_SPPAGEBUILDER_ADDON_BUTTON_DESC'),
        'category'   => 'Content',
        'icon'       => '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 8a4 4 0 014-4h24a4 4 0 014 4v9a4 4 0 01-4 4h-.5a1 1 0 110-2h.5a2 2 0 002-2V8a2 2 0 00-2-2H4a2 2 0 00-2 2v9a2 2 0 002 2h9a1 1 0 110 2H4a4 4 0 01-4-4V8z" fill="currentColor"/><path opacity=".5" fill-rule="evenodd" clip-rule="evenodd" d="M16.004 12.669l1.526 9.46c.05.408.508.611.864.408l2.645-1.882 3.612 5.137c.508.661 2.034-.407 1.577-1.068l-3.612-5.188 2.696-1.832c.305-.254.305-.762-.05-.966l-8.393-4.68a.604.604 0 00-.865.611z" fill="currentColor"/></svg>',
        'inline'     => [
            'contenteditable' => true,
            'buttons'         => [
                'button_general_options' => [
                    'action'   => 'dropdown',
                    'icon'     => 'addon::button',
                    'tooltip'     => Text::_('COM_SPPAGEBUILDER_ADDON_BUTTON'),
                    'fieldset' => [
                        'tab_groups' => [
                            'button' => [
                                'fields' => [
                                    [
                                        'text' => [
                                            'type' => 'text',
                                            'title' => JText::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_TEXT'),
                                            'desc' => JText::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_TEXT_DESC'),
                                            'inline' => true,
                                            'std'  => 'Button'
                                        ],
                                        'type' => [
                                            'type'   => 'select',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_STYLE'),
                                            'desc'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_STYLE_DESC'),
                                            'values' => [
                                                'default'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_DEFAULT'),
                                                'primary'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_PRIMARY'),
                                                'secondary' => Text::_('COM_SPPAGEBUILDER_GLOBAL_SECONDARY'),
                                                'success'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_SUCCESS'),
                                                'info'      => Text::_('COM_SPPAGEBUILDER_GLOBAL_INFO'),
                                                'warning'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_WARNING'),
                                                'danger'    => Text::_('COM_SPPAGEBUILDER_GLOBAL_DANGER'),
                                                'dark'      => Text::_('COM_SPPAGEBUILDER_GLOBAL_DARK'),
                                                'link'      => Text::_('COM_SPPAGEBUILDER_GLOBAL_LINK'),
                                                'custom'    => Text::_('COM_SPPAGEBUILDER_GLOBAL_CUSTOM'),
                                            ],
                                            'inline' => true,
                                            'std'    => 'custom',
                                        ],

                                        'link_button_padding_bottom' => [
                                            'type'    => 'slider',
                                            'title'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_PADDING_BOTTOM'),
                                            'max'     => 100,
                                            'depends' => [['type', '=', 'link']],
                                        ],

                                        'appearance' => [
                                            'type'   => 'select',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_APPEARANCE'),
                                            'desc'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_APPEARANCE_DESC'),
                                            'values' => [
                                                ''         => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_APPEARANCE_FLAT'),
                                                'gradient' => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_APPEARANCE_GRADIENT'),
                                                'outline'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_APPEARANCE_OUTLINE'),
                                                // '3d' => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_APPEARANCE_3D'), // will be removed
                                            ],
                                            'std'     => '',
                                            'depends' => [
                                                ['type', '!=', 'link'],
                                            ],
                                            'inline' => true,
                                        ],

                                        'shape' => [
                                            'type'   => 'select',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SHAPE'),
                                            'desc'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SHAPE_DESC'),
                                            'values' => [
                                                'rounded' => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SHAPE_ROUNDED'),
                                                'square'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SHAPE_SQUARE'),
                                                'round'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SHAPE_ROUND'),
                                            ],
                                            'std'   => 'rounded',
                                            'depends' => [
                                                ['type', '!=', 'link'],
                                            ],
                                            'inline' => true,
                                        ],

                                        'block' => [
                                            'type'   => 'radio',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_BLOCK'),
                                            'desc'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_BLOCK_DESC'),
                                            'values' => [
                                                ''               => Text::_('JNO'),
                                                'sppb-btn-block' => Text::_('JYES'),
                                            ],
                                            'depends' => [
                                                ['type', '!=', 'link'],
                                            ],
                                        ],

                                        'size' => [
                                            'type'   => 'select',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SIZE'),
                                            'desc'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SIZE_DESC'),
                                            'values' => [
                                                ''       => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SIZE_DEFAULT'),
                                                'lg'     => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SIZE_LARGE'),
                                                'xlg'    => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SIZE_XLARGE'),
                                                'sm'     => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SIZE_SMALL'),
                                                'xs'     => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_SIZE_EXTRA_SAMLL'),
                                                'custom' => Text::_('COM_SPPAGEBUILDER_GLOBAL_CUSTOM'),
                                            ],
                                            'inline' => true,
                                        ],

                                        'button_padding' => [
                                            'type'       => 'padding',
                                            'title'      => Text::_('COM_SPPAGEBUILDER_GLOBAL_PADDING'),
                                            'std'        => '',
                                            'responsive' => true,
                                            'depends'    => [
                                                ['type', '=', 'custom'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],

                            'icon' => [
                                'fields' => [
                                    [
                                        'icon' => [
                                            'type'  => 'icon',
                                            'title' => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_ICON'),
                                        ],

                                        'icon_position' => [
                                            'type'   => 'radio',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_ICON_POSITION'),
                                            'values' => [
                                                'left'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_LEFT'),
                                                'right' => Text::_('COM_SPPAGEBUILDER_GLOBAL_RIGHT'),
                                            ],
                                            'std' => 'left',
                                        ],

                                        'icon_margin' => [
                                            'type'       => 'margin',
                                            'title'      => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_ICON_MARGIN'),
                                            'responsive' => true,
                                            'std'        => ['xxl' => '', 'xl' => '0px 0px 0px 0px', 'lg' => '', 'md' => '', 'sm' => '', 'xs' => ''],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                'button_typography_options' => [
                    'type'     => 'icon-text',
                    'icon'     => 'typography',
                    'action'   => 'dropdown',
                    'tooltip'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_TYPOGRAPHY'),
                    'fieldset' => [
                        'basic' => [
                            'typography' => [
                                'type'      => 'typography',
                                'fallbacks' => [
                                    'font'           => 'font_family',
                                    'size'           => 'fontsize',
                                    'letter_spacing' => 'letterspace',
                                    'uppercase'      => 'font_style.uppercase',
                                    'italic'         => 'font_style.italic',
                                    'underline'      => 'font_style.underline',
                                    'weight'         => 'font_style.weight',
                                ],
                            ],
                        ],
                    ],
                ],

                'button_link_options' => [
                    'action'   => 'dropdown',
                    'icon'     => 'link',
                    'tooltip'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_URL'),
                    'fieldset' => [
                        'basic' => [
                            'url' => [
                                'type'  => 'link',
                                'title' => Text::_('COM_SPPAGEBUILDER_GLOBAL_BUTTON_URL'),
                                'mediaType' => 'attachment'
                            ],
                        ],
                    ],
                ],

                'button_color_options' => [
                    'action'  => 'dropdown',
                    'type'    => 'placeholder',
                    'tooltip'     => Text::_('COM_SPPAGEBUILDER_GLOBAL_COLOR'),
                    'default' => '#3366FF',
                    'depends' => [
                        ['type', '=', 'custom'],
                        ['appearance', '!=', 'gradient'],
                    ],
                    'placeholder' => [
                        'type'      => 'HTMLElement',
                        'element'   => 'div',
                        'selector'  => '.builder-color-picker',
                        'attribute' => [
                            'type'     => 'style',
                            'property' => 'background'
                        ],
                        'display_field' => 'background_color',
                    ],
                    'fieldset' => [
                        'tab_groups' => [
                            'general' => [
                                'fields' => [
                                    'background' => [
                                        'background_color' => [
                                            'type'   => 'color',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BACKGROUND_COLOR'),
                                            'std'    => '#3366FF',
                                        ],

                                        'color' => [
                                            'type'   => 'color',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_COLOR'),
                                            'std'    => '#FFFFFF',
                                        ]
                                    ],
                                ]
                            ],

                            'hover' => [
                                'fields' => [
                                    'background' => [
                                        'background_color_hover' => [
                                            'type'    => 'color',
                                            'std'     => '#0037DD',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BACKGROUND_COLOR'),
                                        ],

                                        'color_hover' => [
                                            'type'   => 'color',
                                            'std'    => '#FFFFFF',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_COLOR'),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                'button_gradient_options' => [
                    'action'  => 'dropdown',
                    'type'    => 'placeholder',
                    'default' => ["color" => "#3366FF", "color2" => "#0037DD", "deg" => "45", "type" => "linear"],
                    'depends' => [
                        ['type', '=', 'custom'],
                        ['appearance', '=', 'gradient'],
                    ],
                    'placeholder' => [
                        'type'      => 'HTMLElement',
                        'element'   => 'div',
                        'selector'  => '.builder-color-picker',
                        'attribute' => [
                            'type'     => 'style',
                            'property' => 'background'
                        ],
                        'display_field' => 'background_gradient',
                    ],
                    'fieldset' => [
                        'tab_groups' => [
                            'normal' => [
                                'fields'        => [
                                    [
                                        'color_gradient' => [
                                            'type'   => 'color',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_TEXT_COLOR'),
                                            'std'    => '#FFFFFF',
                                        ],

                                        'background_gradient' => [
                                            'type' => 'gradient',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BACKGROUND_COLOR'),
                                            'std'  => [
                                                "color"  => "#3366FF",
                                                "color2" => "#0037DD",
                                                "deg"    => "45",
                                                "type"   => "linear"
                                            ],
                                        ],
                                    ],
                                ]
                            ],

                            'hover' => [
                                'fields'        => [
                                    [
                                        'color_gradient_hover' => [
                                            'type'   => 'color',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_TEXT_COLOR'),
                                            'std'    => '#FFFFFF',
                                        ],

                                        'background_gradient_hover' => [
                                            'type'  => 'gradient',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BACKGROUND_COLOR'),
                                            'std'   => [
                                                "color"  => "#0037DD",
                                                "color2" => "#3366FF",
                                                "deg"    => "45",
                                                "type"   => "linear"
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                'button_link_style_options' => [
                    'action'  => 'dropdown',
                    'type'    => 'placeholder',
                    'default' => '#3366FF',
                    'depends' => [
                        ['type', '=', 'link'],
                    ],
                    'placeholder' => [
                        'type'      => 'HTMLElement',
                        'element'   => 'div',
                        'selector'  => '.builder-color-picker',
                        'attribute' => [
                            'type'     => 'style',
                            'property' => 'background'
                        ],
                        'display_field' => 'link_button_color',
                    ],

                    'fieldset' => [
                        'tab_groups' => [
                            'normal' => [
                                'fields'        => [
                                    [
                                        'link_button_color' => [
                                            'type'   => 'color',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_COLOR'),
                                            'std'    => '#3366FF',
                                        ],

                                        'link_button_border_width' => [
                                            'type'    => 'slider',
                                            'title'   => Text::_('COM_SPPAGEBUILDER_GLOBAL_BORDER_WIDTH'),
                                            'max'     => 10,
                                            'std'     => 1,
                                        ],

                                        'link_border_color' => [
                                            'type'   => 'color',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BORDER_COLOR'),
                                            'std'    => '#3366FF',
                                        ]
                                    ],
                                ]
                            ],

                            'hover' => [
                                'fields'        => [
                                    [
                                        'link_button_hover_color' => [
                                            'type'   => 'color',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_COLOR'),
                                            'std'    => '#0037DD',
                                        ],

                                        'link_button_border_hover_color' => [
                                            'type'   => 'color',
                                            'title'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_BORDER_COLOR'),
                                            'std'    => '#0037DD',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                'button_alignment_separator' => [
                    'action' => 'separator',
                ],

                'button_alignment_options' => [
                    'action'      => 'dropdown',
                    'type'        => 'placeholder',
                    'tooltip'  => Text::_('COM_SPPAGEBUILDER_GLOBAL_ALIGNMENT'),
                    'style'       => 'inline',
                    'showCaret'   => true,
                    'placeholder' => [
                        'type'    => 'list',
                        'options' => [
                            'left'   => ['icon' => 'textAlignLeft'],
                            'center' => ['icon' => 'textAlignCenter'],
                            'right'  => ['icon' => 'textAlignRight'],
                        ],
                        'display_field' => 'alignment'
                    ],
                    'default' => [
                        'xl' => 'left',
                    ],
                    'fieldset' => [
                        'basic' => [
                            'alignment' => [
                                'type'              => 'alignment',
                                'inline'            => true,
                                'responsive'        => true,
                                'available_options' => ['left', 'center', 'right'],
                                'std'               => [
                                    'xxl' => '',
                                    'xl' => 'center',
                                    'lg' => '',
                                    'md' => '',
                                    'sm' => '',
                                    'xs' => '',
                                ]
                            ]
                        ]
                    ]
                ],

                // 'button_misc_separator' => [
                //     'action' => 'separator',
                // ],

                // 'bold' => [
                //     'action'  => 'bold',
                //     'icon'    => 'bold',
                //     'tooltip' => 'Click here to bold selected text'
                // ],
                // 'italic' => [
                //     'action'  => 'italic',
                //     'icon'    => 'italic',
                //     'tooltip' => 'Click here to italic selected text'
                // ],
                // 'underline' => [
                //     'action'  => 'underline',
                //     'icon'    => 'underline',
                //     'tooltip' => 'Click here to underline selected text'
                // ],
            ]
        ],
        'attr' => [],
    ]
);
