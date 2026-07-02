<?php

return [
    'role_permissions' => [
        'super_admin' => ['*'],
        'admin' => [
            'dashboard.view',
            'rooms.view',
            'rooms.manage',
            'room_schedule.view',
            'room_schedule.manage',
            'calendar_events.view',
            'calendar_events.manage',
            'pricing_rules.view',
            'pricing_rules.manage',
            'bookings.view',
            'bookings.manage',
            'payments.view',
            'payments.manage',
            'reports.view',
            'settings.view',
            'settings.manage',
            'credits.view',
            'venue_stock.view',
            'shop_products.view',
        ],
        'front_desk' => [
            'dashboard.view',
            'room_schedule.view',
            'calendar_events.view',
            'bookings.view',
            'bookings.manage',
            'payments.view',
            'payments.manage',
        ],
        'audit' => [
            'dashboard.view',
            'payments.view',
            'reports.view',
        ],
    ],

    'sidebar_sections' => [
        [
            'title' => null,
            'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'permission' => 'dashboard.view'],
            ],
        ],
        [
            'title' => 'Rooms',
            'items' => [
                ['label' => 'Rooms', 'route' => 'admin.sections.rooms', 'permission' => 'rooms.view'],
                ['label' => 'Room Schedule', 'route' => 'admin.sections.room-schedule', 'permission' => 'room_schedule.view'],
                ['label' => 'Calendar & Events', 'route' => 'admin.sections.calendar-events', 'permission' => 'calendar_events.view'],
                ['label' => 'Pricing Rules', 'route' => 'admin.sections.pricing-rules', 'permission' => 'pricing_rules.view'],
            ],
        ],
        [
            'title' => 'Bookings',
            'items' => [
                ['label' => 'Bookings', 'route' => 'admin.bookings.index', 'permission' => 'bookings.view'],
                ['label' => 'Payments', 'route' => 'admin.sections.payments', 'permission' => 'payments.view'],
            ],
        ],
        [
            'title' => 'Members',
            'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'permission' => 'users.view'],
            ],
        ],
        [
            'title' => 'Reports & System',
            'items' => [
                ['label' => 'Reports', 'route' => 'admin.sections.reports', 'permission' => 'reports.view'],
                ['label' => 'Admin Roles', 'route' => 'admin.sections.admin-roles', 'permission' => 'admin_roles.view'],
                ['label' => 'Settings', 'route' => 'admin.sections.settings', 'permission' => 'settings.view'],
            ],
        ],
    ],

    'access_columns' => [
        ['key' => 'super_admin', 'label' => 'Super Admin'],
        ['key' => 'admin', 'label' => 'Admin'],
        ['key' => 'front_desk', 'label' => 'Front Desk'],
        ['key' => 'audit', 'label' => 'Audit'],
    ],

    'access_matrix' => [
        ['module' => 'Dashboard', 'super_admin' => 'full', 'admin' => 'full', 'front_desk' => 'full', 'audit' => 'read'],
        ['module' => 'Rooms', 'super_admin' => 'full', 'admin' => 'full', 'front_desk' => 'none', 'audit' => 'none'],
        ['module' => 'Room Schedule', 'super_admin' => 'full', 'admin' => 'full', 'front_desk' => 'read', 'audit' => 'none'],
        ['module' => 'Calendar & Events', 'super_admin' => 'full', 'admin' => 'full', 'front_desk' => 'read', 'audit' => 'none'],
        ['module' => 'Bookings', 'super_admin' => 'full', 'admin' => 'full', 'front_desk' => 'full', 'audit' => 'none'],
        ['module' => 'Payments', 'super_admin' => 'full', 'admin' => 'full', 'front_desk' => 'full', 'audit' => 'read'],
        ['module' => 'Users', 'super_admin' => 'full', 'admin' => 'none', 'front_desk' => 'none', 'audit' => 'none'],
        ['module' => 'Credits', 'super_admin' => 'full', 'admin' => 'read', 'front_desk' => 'none', 'audit' => 'none'],
        ['module' => 'Pricing Rules', 'super_admin' => 'full', 'admin' => 'full', 'front_desk' => 'none', 'audit' => 'none'],
        ['module' => 'Reports', 'super_admin' => 'full', 'admin' => 'read', 'front_desk' => 'none', 'audit' => 'read'],
        ['module' => 'Venue Stock', 'super_admin' => 'full', 'admin' => 'read', 'front_desk' => 'none', 'audit' => 'none'],
        ['module' => 'Shop Products', 'super_admin' => 'full', 'admin' => 'read', 'front_desk' => 'none', 'audit' => 'none'],
        ['module' => 'Admin Roles', 'super_admin' => 'full', 'admin' => 'none', 'front_desk' => 'none', 'audit' => 'none'],
        ['module' => 'Settings', 'super_admin' => 'full', 'admin' => 'full', 'front_desk' => 'none', 'audit' => 'none'],
    ],
];
