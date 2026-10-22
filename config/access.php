<?php

return [
    'permissions' => [
        'settings.view' => ['name' => 'Melihat pengaturan', 'module' => 'settings'],
        'branches.view' => ['name' => 'Melihat cabang', 'module' => 'organization'],
        'branches.manage' => ['name' => 'Mengelola cabang', 'module' => 'organization'],
        'warehouses.manage' => ['name' => 'Mengelola gudang', 'module' => 'organization'],
        'registers.manage' => ['name' => 'Mengelola register', 'module' => 'organization'],
        'access.view' => ['name' => 'Melihat hak akses', 'module' => 'access'],
        'access.manage' => ['name' => 'Mengelola hak akses', 'module' => 'access'],
        'approvals.view' => ['name' => 'Melihat persetujuan', 'module' => 'approval'],
        'approvals.request' => ['name' => 'Mengajukan persetujuan', 'module' => 'approval'],
        'approvals.approve' => ['name' => 'Menyetujui permintaan', 'module' => 'approval'],
        'approval_limits.manage' => ['name' => 'Mengelola batas persetujuan', 'module' => 'approval'],
        'audit.view' => ['name' => 'Melihat audit log', 'module' => 'audit'],
    ],

    'roles' => [
        'super_admin' => ['name' => 'Super Admin', 'permissions' => ['*']],
        'owner' => ['name' => 'Owner', 'permissions' => ['*']],
        'store_manager' => [
            'name' => 'Manager Toko',
            'permissions' => [
                'settings.view', 'branches.view', 'warehouses.manage', 'registers.manage',
                'access.view', 'approvals.view', 'approvals.request', 'approvals.approve',
                'approval_limits.manage', 'audit.view',
            ],
        ],
        'purchasing' => ['name' => 'Purchasing', 'permissions' => ['approvals.view', 'approvals.request']],
        'inventory' => ['name' => 'Inventory / Gudang', 'permissions' => ['approvals.view', 'approvals.request']],
        'cashier' => ['name' => 'Kasir', 'permissions' => ['approvals.request']],
        'finance' => ['name' => 'Finance', 'permissions' => ['approvals.view', 'approvals.request']],
        'marketing' => ['name' => 'Marketing', 'permissions' => ['approvals.view', 'approvals.request']],
    ],
];
