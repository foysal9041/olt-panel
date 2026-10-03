<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | Here you can change the default title of your admin panel.
    |
    | For detailed instructions you can look the title section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'title' => 'Sunlit Network ERP',
    'title_prefix' => '',
    'title_postfix' => '',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    |
    | Here you can activate the favicon.
    |
    | For detailed instructions you can look the favicon section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_ico_only' => false,
    'use_full_favicon' => false,

    /*
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    |
    | Here you can allow or not the use of external google fonts. Disabling the
    | google fonts may be useful if your admin panel internet access is
    | restricted somehow.
    |
    | For detailed instructions you can look the google fonts section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'google_fonts' => [
        'allowed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Logo
    |--------------------------------------------------------------------------
    |
    | Here you can change the logo of your admin panel.
    |
    | For detailed instructions you can look the logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    // Two-image setup: a square icon (logo_img) shown when the sidebar is
    // collapsed to its mini/icon-only width, and the full wide wordmark
    // (logo_img_xl) shown when it's expanded — AdminLTE fades between them
    // based on sidebar state. A single wide logo alone would overflow/shift
    // in the collapsed (~74px) sidebar width, since that slot is sized for
    // a small square icon.
    'logo' => '',
    'logo_img' => 'images/logo-icon.png?v=' . @filemtime(public_path('images/logo-icon.png')),
    'logo_img_class' => 'brand-image',
    'logo_img_xl' => 'images/logo.png?v=' . @filemtime(public_path('images/logo.png')),
    'logo_img_xl_class' => 'brand-image-xl',
    'logo_img_alt' => 'Sunlit Network',

    /*
    |--------------------------------------------------------------------------
    | Authentication Logo
    |--------------------------------------------------------------------------
    |
    | Here you can setup an alternative logo to use on your login and register
    | screens. When disabled, the admin panel logo will be used instead.
    |
    | For detailed instructions you can look the auth logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'auth_logo' => [
        'enabled' => false,
        'img' => [
            'path' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt' => 'Auth Logo',
            'class' => '',
            'width' => 50,
            'height' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preloader Animation
    |--------------------------------------------------------------------------
    |
    | Here you can change the preloader animation configuration. Currently, two
    | modes are supported: 'fullscreen' for a fullscreen preloader animation
    | and 'cwrapper' to attach the preloader animation into the content-wrapper
    | element and avoid overlapping it with the sidebars and the top navbar.
    |
    | For detailed instructions you can look the preloader section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'preloader' => [
        'enabled' => true,
        'mode' => 'cwrapper',
        'img' => [
            'path' => 'images/logo.png?v=' . @filemtime(public_path('images/logo.png')),
            'alt' => 'Sunlit Network Preloader Image',
            'effect' => 'animation__shake',
            'width' => 120,
            'height' => 47,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    |
    | Here you can activate and change the user menu.
    |
    | For detailed instructions you can look the user menu section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'usermenu_enabled' => true,
    'usermenu_header' => false,
    'usermenu_header_class' => 'bg-primary',
    'usermenu_image' => false,
    'usermenu_desc' => false,
    'usermenu_profile_url' => false,

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Here we change the layout of your admin panel.
    |
    | For detailed instructions you can look the layout section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => true,
    'layout_fixed_navbar' => true,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the authentication views.
    |
    | For detailed instructions you can look the auth classes section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_auth_card' => 'card-outline card-primary',
    'classes_auth_header' => '',
    'classes_auth_body' => '',
    'classes_auth_footer' => '',
    'classes_auth_icon' => '',
    'classes_auth_btn' => 'btn-flat btn-primary',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the admin panel.
    |
    | For detailed instructions you can look the admin panel classes here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_body' => '',
    'classes_brand' => '',
    'classes_brand_text' => '',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'sidebar-dark-primary elevation-4',
    'classes_sidebar_nav' => '',
    'classes_topnav' => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar of the admin panel.
    |
    | For detailed instructions you can look the sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'sidebar_mini' => 'lg',
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_collapse_remember' => true,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 300,

    /*
    |--------------------------------------------------------------------------
    | Control Sidebar (Right Sidebar)
    |--------------------------------------------------------------------------
    |
    | Here we can modify the right sidebar aka control sidebar of the admin panel.
    |
    | For detailed instructions you can look the right sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'right_sidebar' => false,
    'right_sidebar_icon' => 'fas fa-cogs',
    'right_sidebar_theme' => 'dark',
    'right_sidebar_slide' => true,
    'right_sidebar_push' => true,
    'right_sidebar_scrollbar_theme' => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide' => 'l',

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Here we can modify the url settings of the admin panel.
    |
    | For detailed instructions you can look the urls section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_route_url' => false,
    'dashboard_url' => 'dashboard',
    'logout_url' => 'logout',
    'login_url' => 'login',
    'register_url' => false,
    'password_reset_url' => 'password/reset',
    'password_email_url' => 'password/email',
    'profile_url' => false,
    'disable_darkmode_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Laravel Asset Bundling
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Laravel Asset Bundling option for the admin panel.
    | Currently, the next modes are supported: 'mix', 'vite' and 'vite_js_only'.
    | When using 'vite_js_only', it's expected that your CSS is imported using
    | JavaScript. Typically, in your application's 'resources/js/app.js' file.
    | If you are not using any of these, leave it as 'false'.
    |
    | For detailed instructions you can look the asset bundling section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'laravel_asset_bundling' => false,
    'laravel_css_path' => 'css/app.css',
    'laravel_js_path' => 'js/app.js',

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar/top navigation of the admin panel.
    |
    | For detailed instructions you can look here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'menu' => [

    // Top bar: switch the interface language (label comes from lang/*/menu.php).
    [
        'text' => 'lang_switch',
        'url' => 'locale/toggle',
        'icon' => 'fas fa-language',
        'topnav_right' => true,
    ],

    // Sections show only when the user can open something in them.
    [
        'type' => 'sidebar-menu-search',
        'text' => 'Search menu…',
    ],

    ['header' => 'OVERVIEW'],
    [
        'text' => 'Dashboard',
        'url'  => '/',
        'icon' => 'fas fa-home',
        'icon_color' => 'primary',
    ],

    ['header' => 'MY WORK'],
    [
        'text' => 'My Tasks & To-do',
        'url'  => 'my/tasks',
        'icon' => 'fas fa-check-square',
        'icon_color' => 'teal',
        'key'  => 'my-tasks',
        'active' => ['my/tasks', 'my/tasks/*'],
    ],
    [
        'text' => 'Tickets',
        'url'  => 'tickets',
        'icon' => 'fas fa-ticket-alt',
        'icon_color' => 'danger',
        'key'  => 'tickets',
        'active' => ['tickets', 'tickets/*'],
    ],
    [
        'text' => 'My Leave',
        'url'  => 'my/leave',
        'icon' => 'fas fa-umbrella-beach',
        'icon_color' => 'info',
    ],

    ['header' => 'OPERATIONS', 'can' => ['access-olt', 'access-attendance', 'access-latency']],
    [
        'text' => 'NOC',
        'icon' => 'fas fa-satellite-dish',
        'icon_color' => 'info',
        'submenu' => [
            [
                'text' => 'NOC Dashboard',
                'url'  => 'olt/dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'can'  => 'access-olt-dashboard',
            ],
            [
                'text' => 'OLTs',
                'url'  => 'olt',
                'icon' => 'fas fa-network-wired',
                'can'  => 'access-olt-manage',
            ],
            [
                'text' => 'Switches',
                'icon' => 'fas fa-server',
                'can'  => 'access-olt-switches',
                'submenu' => [
                    [
                        'text' => 'All Switches',
                        'url'  => 'switches',
                        'icon' => 'fas fa-list',
                        'can'  => 'access-olt-switches',
                    ],
                    [
                        'text' => 'Port Events',
                        'url'  => 'switch-events',
                        'icon' => 'fas fa-history',
                        'can'  => 'access-olt-switches',
                    ],
                ],
            ],
            [
                'text' => 'Latency',
                'icon' => 'fas fa-wave-square',
                'can'  => 'access-latency',
                'submenu' => [
                    [
                        'text' => 'Latency Graphs',
                        'url'  => 'latency',
                        'icon' => 'fas fa-chart-area',
                        'can'  => 'access-latency-graphs',
                    ],
                    [
                        'text' => 'Manage Targets',
                        'url'  => 'latency/targets',
                        'icon' => 'fas fa-bullseye',
                        'can'  => 'access-latency-targets',
                    ],
                ],
            ],
            [
                'text' => 'IP & VLAN',
                'icon' => 'fas fa-sitemap',
                'submenu' => [
                    [
                        'text' => 'Zones',
                        'url'  => 'zones',
                        'icon' => 'fas fa-map-marked-alt',
                        'can'  => 'access-olt-zones',
                    ],
                    [
                        'text' => 'VLAN Management',
                        'url'  => 'vlans',
                        'icon' => 'fas fa-stream',
                        'can'  => 'access-olt-vlans',
                    ],
                    [
                        'text' => 'IP Management',
                        'url'  => 'ip-pools',
                        'icon' => 'fas fa-globe',
                        'can'  => 'access-olt-ip',
                    ],
                    [
                        'text' => 'NTTN Links',
                        'url'  => 'nttn-links',
                        'icon' => 'fas fa-project-diagram',
                        'can'  => 'access-olt-nttn',
                    ],
                ],
            ],
            [
                'text' => 'Technical Support',
                'url'  => 'support-contacts',
                'icon' => 'fas fa-headset',
                'can'  => 'access-olt-support',
            ],
        ],
    ],

    [
        'text' => 'HR & Attendance',
        'icon_color' => 'success',
        'icon' => 'fas fa-fingerprint',
        'submenu' => [
            [
                'text' => 'Dashboard',
                'url'  => 'attendance/dashboard',
                'icon' => 'fas fa-chart-line',
                'can'  => 'access-attendance-dashboard',
            ],
            [
                'text' => 'Attendance Report',
                'url'  => 'attendance/report',
                'icon' => 'fas fa-calendar-check',
                'can'  => 'access-attendance-report',
            ],
            [
                'text' => 'Absence Report',
                'url'  => 'attendance/absence',
                'icon' => 'fas fa-calendar-times',
                'can'  => 'access-attendance-absence',
            ],
            [
                'text' => 'Employees',
                'url'  => 'attendance/employees',
                'icon' => 'fas fa-id-badge',
                'can'  => 'access-attendance-employees',
            ],
            [
                'text' => 'Leave Management',
                'url'  => 'attendance/leaves',
                'icon' => 'fas fa-plane-departure',
                'can'  => 'access-attendance-leaves',
                'key'  => 'leave-requests',
            ],
            [
                'text' => 'HR Letters',
                'url'  => 'attendance/letters',
                'icon' => 'fas fa-file-signature',
                'can'  => 'access-attendance-letters',
                'active' => ['attendance/letters', 'attendance/letters/*'],
            ],
            [
                'text' => 'ID Cards',
                'url'  => 'attendance/id-cards',
                'icon' => 'fas fa-id-card',
                'can'  => 'access-attendance-idcards',
                'active' => ['attendance/id-cards', 'attendance/id-cards/*'],
            ],
            [
                'text' => 'Duty Shifts',
                'url'  => 'attendance/shifts',
                'icon' => 'fas fa-business-time',
                'can'  => 'access-attendance-shifts',
            ],
            [
                'text' => 'Devices (F18)',
                'url'  => 'attendance/devices',
                'icon' => 'fas fa-microchip',
                'can'  => 'access-attendance-devices',
            ],
        ],
    ],

    ['header' => 'FINANCE', 'can' => ['access-accounts', 'access-inventory']],
    [
        'text' => 'Accounts',
        'icon_color' => 'warning',
        'icon' => 'fas fa-file-invoice-dollar',
        'submenu' => [
            [
                'text' => 'Dashboard',
                'url'  => 'accounts/dashboard',
                'icon' => 'fas fa-chart-line',
                'can'  => 'access-accounts-dashboard',
            ],
            [
                'text' => 'Daily Cash Book',
                'url'  => 'accounts/cashbook',
                'icon' => 'fas fa-book-open',
                'can'  => 'access-accounts-cashbook',
            ],
            [
                'text' => 'Income & Expenses',
                'url'  => 'accounts/transactions',
                'icon' => 'fas fa-money-bill-wave',
                'can'  => 'access-accounts-transactions',
            ],
            [
                'text' => 'Zone Settlement',
                'url'  => 'accounts/settlements',
                'icon' => 'fas fa-file-excel',
                'can'  => 'access-accounts-settlements',
            ],
            [
                'text' => 'Bandwidth Billing',
                'url'  => 'accounts/billing',
                'icon' => 'fas fa-tachometer-alt',
                'can'  => 'access-accounts-billing',
            ],
            [
                'text' => 'Net Profit & Shares',
                'url'  => 'accounts/profit',
                'icon' => 'fas fa-chart-line',
                'can'  => 'access-accounts-profit',
            ],
            [
                'text' => 'Partners',
                'url'  => 'accounts/partners',
                'icon' => 'fas fa-user-tie',
                'can'  => 'access-accounts-partners',
            ],
            [
                'text' => 'Salary Sheet',
                'url'  => 'accounts/salaries',
                'icon' => 'fas fa-money-check-alt',
                'can'  => 'access-accounts-salaries',
            ],
            [
                'text' => 'Customers',
                'url'  => 'accounts/customers',
                'icon' => 'fas fa-users',
                'can'  => 'access-accounts-customers',
            ],
        ],
    ],

    [
        'text' => 'Inventory & Assets',
        'icon_color' => 'teal',
        'icon' => 'fas fa-boxes',
        'submenu' => [
            [
                'text' => 'Summary',
                'url'  => 'inventory',
                'icon' => 'fas fa-chart-pie',
                'can'  => 'access-inventory-summary',
            ],
            [
                'text' => 'Products & Stock',
                'url'  => 'inventory/items',
                'icon' => 'fas fa-box',
                'can'  => 'access-inventory-stock',
                'active' => ['inventory/items', 'inventory/items/*'],
            ],
            [
                'text' => 'Stock Entries',
                'url'  => 'inventory/entries',
                'icon' => 'fas fa-exchange-alt',
                'can'  => 'access-inventory-stock',
                'active' => ['inventory/entries', 'inventory/entries/*'],
            ],
            [
                'text' => 'Company Assets',
                'url'  => 'inventory/assets',
                'icon' => 'fas fa-building',
                'can'  => 'access-inventory-assets',
            ],
        ],
    ],
    ['header' => 'ADMINISTRATION', 'can' => ['access-settings-general', 'access-settings-users', 'access-settings-telegram']],
    [
        'text' => 'Settings',
        'icon' => 'fas fa-cog',
        'icon_color' => 'secondary',
        'submenu' => [
            [
                'text' => 'General',
                'url'  => 'settings',
                'icon' => 'fas fa-sliders-h',
                'can'  => 'access-settings-general',
            ],
            [
                'text' => 'Users & Roles',
                'url'  => 'users',
                'icon' => 'fas fa-user-shield',
                'can'  => 'access-settings-users',
            ],
            [
                'text' => 'Activity Log',
                'url'  => 'activity',
                'icon' => 'fas fa-history',
                'can'  => 'access-settings-users',
            ],
            [
                'text' => 'Telegram',
                'url'  => 'settings/telegram',
                'icon' => 'fab fa-telegram-plane',
                'can'  => 'access-settings-telegram',
            ],
            [
                'text' => 'WhatsApp',
                'url'  => 'settings/whatsapp',
                'icon' => 'fab fa-whatsapp',
                'can'  => 'access-settings-telegram',
            ],
        ],
    ],

    ],


    /*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
    |
    | Here we can modify the menu filters of the admin panel.
    |
    | For detailed instructions you can look the menu filters section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'filters' => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        App\Menu\WorkBadgeFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins Initialization
    |--------------------------------------------------------------------------
    |
    | Here we can modify the plugins used inside the admin panel.
    |
    | For detailed instructions you can look the plugins section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Plugins-Configuration
    |
    */

    'plugins' => [
        'Datatables' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],
        // Served from public/vendor (AdminLTE's bundled Select2 4.0.13 and its
        // Bootstrap 4 theme) — the old cdnjs theme link returned 404.
        'Select2' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/select2/js/select2.full.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/select2/css/select2.min.css',
                ],
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/select2-bootstrap4-theme/select2-bootstrap4.min.css',
                ],
            ],
        ],
        'Chartjs' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.bundle.min.js',
                ],
            ],
        ],
        'Sweetalert2' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.jsdelivr.net/npm/sweetalert2@8',
                ],
            ],
        ],
        'Pace' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/themes/blue/pace-theme-center-radar.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',
                ],
            ],
        ],
        // Last, so the panel's own styles override the plugins' above.
        'CustomTheme' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'css/custom.css?v=' . @filemtime(public_path('css/custom.css')),
                ],
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'js/custom.js?v=' . @filemtime(public_path('js/custom.js')),
                ],
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'js/live-refresh.js?v=' . @filemtime(public_path('js/live-refresh.js')),
                ],
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'css/module-pages.css?v=' . @filemtime(public_path('css/module-pages.css')),
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IFrame
    |--------------------------------------------------------------------------
    |
    | Here we change the IFrame mode configuration. Note these changes will
    | only apply to the view that extends and enable the IFrame mode.
    |
    | For detailed instructions you can look the iframe mode section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/IFrame-Mode-Configuration
    |
    */

    'iframe' => [
        'default_tab' => [
            'url' => null,
            'title' => null,
        ],
        'buttons' => [
            'close' => true,
            'close_all' => true,
            'close_all_other' => true,
            'scroll_left' => true,
            'scroll_right' => true,
            'fullscreen' => true,
        ],
        'options' => [
            'loading_screen' => 1000,
            'auto_show_new_tab' => true,
            'use_navbar_items' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Livewire support.
    |
    | For detailed instructions you can look the livewire here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'livewire' => false,
];
