<?php namespace RainLab\Pages;

use Event;
use Backend;
use RainLab\Pages\Classes\Controller;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\Router;
use Cms\Classes\Theme;
use Cms\Classes\Controller as CmsController;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'rainlab.pages::lang.plugin.name',
            'description' => 'rainlab.pages::lang.plugin.description',
            'author' => 'Alexey Bobkov, Samuel Georges',
            'icon' => 'icon-files-o',
            'homepage' => 'https://github.com/rainlab/pages-plugin'
        ];
    }

    public function registerComponents()
    {
        return [
            \RainLab\Pages\Components\ChildPages::class => 'childPages',
            \RainLab\Pages\Components\StaticPage::class => 'staticPage',
            \RainLab\Pages\Components\StaticMenu::class => 'staticMenu',
            \RainLab\Pages\Components\StaticBreadcrumbs::class => 'staticBreadcrumbs'
        ];
    }

    public function registerPermissions()
    {
        return [
            'rainlab.pages.manage_pages' => [
                'tab'   => 'rainlab.pages::lang.page.tab',
                'order' => 200,
                'label' => 'rainlab.pages::lang.page.manage_pages'
            ],
            'rainlab.pages.manage_menus' => [
                'tab'   => 'rainlab.pages::lang.page.tab',
                'order' => 200,
                'label' => 'rainlab.pages::lang.page.manage_menus'
                ],
            'rainlab.pages.manage_content' => [
                'tab'   => 'rainlab.pages::lang.page.tab',
                'order' => 200,
                'label' => 'rainlab.pages::lang.page.manage_content'
            ]
        ];
    }

    public function registerNavigation()
    {
        return [
            'pages' => [
                'label'       => 'rainlab.pages::lang.plugin.name',
                'url'         => Backend::url('rainlab/pages'),
                'icon'        => 'icon-files-o',
                'iconSvg'     => 'plugins/rainlab/pages/assets/images/pages-icon.svg',
                'permissions' => ['rainlab.pages.*'],
                'order'       => 200,
                'useDropdown' => false,

                'sideMenu' => [
                    'pages' => [
                        'label'       => 'rainlab.pages::lang.page.menu_label',
                        'icon'        => 'icon-files-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'pages'],
                        'permissions' => ['rainlab.pages.manage_pages']
                    ],
                    'menus' => [
                        'label'       => 'rainlab.pages::lang.menu.menu_label',
                        'icon'        => 'icon-sitemap',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'menus'],
                        'permissions' => ['rainlab.pages.manage_menus']
                    ],
                    'content' => [
                        'label'       => 'rainlab.pages::lang.content.menu_label',
                        'icon'        => 'icon-file-text-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'content'],
                        'permissions' => ['rainlab.pages.manage_content']
                    ]
                ]
            ]
        ];
    }

    public function registerFormWidgets()
    {
        return [
            FormWidgets\PagePicker::class => 'staticpagepicker',
            FormWidgets\MenuPicker::class => 'staticmenupicker',
        ];
    }

    public function boot()
    {
        Event::listen('cms.router.beforeRoute', function($url) {
            return Controller::instance()->initCmsPage($url);
        });

        Event::listen('cms.page.beforeRenderPage', function($controller, $page) {
            // Before twig renders
            $twig = $controller->getTwig();
            $loader = $controller->getLoader();
            Controller::instance()->injectPageTwig($page, $loader, $twig);

            // Get rendered content
            $contents = Controller::instance()->getPageContents($page);
            if ($contents && strlen($contents)) {
                return $contents;
            }
        });

        Event::listen('cms.block.render', function($blockName, $blockContents) {
            $page = CmsController::getController()->getPage();

            if (!isset($page->apiBag['staticPage'])) {
                return;
            }

            $contents = Controller::instance()->getPlaceholderContents($page, $blockName, $blockContents);
            if ($contents && strlen($contents)) {
                return $contents;
            }
        });

        Event::listen('cms.pageLookup.listTypes', function() {
            return [
                'static-page'      => 'rainlab.pages::lang.menuitem.static_page',
                'all-static-pages' => ['rainlab.pages::lang.menuitem.all_static_pages', true]
            ];
        });

        Event::listen('pages.menuitem.listTypes', function() {
            return [
                'static-page'      => 'rainlab.pages::lang.menuitem.static_page',
                'all-static-pages' => 'rainlab.pages::lang.menuitem.all_static_pages'
            ];
        });

        Event::listen(['cms.pageLookup.getTypeInfo', 'pages.menuitem.getTypeInfo'], function($type) {
            if ($type == 'url') {
                return [];
            }

            if ($type == 'static-page'|| $type == 'all-static-pages') {
                return StaticPage::getMenuTypeInfo($type);
            }
        });

        Event::listen(['cms.pageLookup.resolveItem', 'pages.menuitem.resolveItem'], function($type, $item, $url, $theme) {
            if ($type == 'static-page' || $type == 'all-static-pages') {
                return StaticPage::resolveMenuItem($item, $url, $theme);
            }
        });

        Event::listen('cms.template.save', function($controller, $template, $type) {
            Plugin::clearCache();
        });

        Event::listen('cms.template.processTwigContent', function($template, $dataHolder) {
            if ($template instanceof \Cms\Classes\Layout) {
                $dataHolder->content = Controller::instance()->parseSyntaxFields($dataHolder->content);
            }
        });

        Event::listen('backend.richeditor.listTypes', function () {
            return [
                'static-page' => 'rainlab.pages::lang.menuitem.static_page',
            ];
        });

        Event::listen('backend.richeditor.getTypeInfo', function ($type) {
            if ($type === 'static-page') {
                return StaticPage::getRichEditorTypeInfo($type);
            }
        });

        Event::listen('system.console.theme.sync.getAvailableModelClasses', function () {
            return [
                Classes\Menu::class,
                Classes\Page::class,
            ];
        });
    }

    /**
     * Register new Twig variables
     * @return array
     */
    public function registerMarkupTags()
    {
        return [
            'filters' => [
                'staticPage' => [\RainLab\Pages\Classes\Page::class, 'url', false]
            ]
        ];
    }

    public static function clearCache()
    {
        $theme = Theme::getEditTheme();

        $router = new Router($theme);
        $router->clearCache();

        StaticPage::clearMenuCache($theme);
        // SnippetManager::clearCache($theme);
    }
}
