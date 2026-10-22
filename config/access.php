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
        'master.view' => ['name' => 'Membuka modul master', 'module' => 'master'],
        'catalog.view' => ['name' => 'Melihat katalog barang', 'module' => 'catalog'],
        'catalog.manage' => ['name' => 'Mengelola katalog barang', 'module' => 'catalog'],
        'suppliers.view' => ['name' => 'Melihat supplier', 'module' => 'suppliers'],
        'suppliers.manage' => ['name' => 'Mengelola supplier', 'module' => 'suppliers'],
        'locations.view' => ['name' => 'Melihat rak dan lokasi', 'module' => 'catalog'],
        'locations.manage' => ['name' => 'Mengelola rak dan lokasi', 'module' => 'catalog'],
        'pricing.view' => ['name' => 'Melihat strata harga', 'module' => 'pricing'],
        'pricing.manage' => ['name' => 'Mengelola strata harga', 'module' => 'pricing'],
        'tax.view' => ['name' => 'Melihat konfigurasi pajak', 'module' => 'pricing'],
        'tax.manage' => ['name' => 'Mengelola konfigurasi pajak', 'module' => 'pricing'],
        'inventory.view' => ['name' => 'Melihat persediaan', 'module' => 'inventory'],
        'inventory.opening.create' => ['name' => 'Mengajukan saldo awal', 'module' => 'inventory'],
        'inventory.opening.approve' => ['name' => 'Menyetujui saldo awal', 'module' => 'inventory'],
        'inventory.transfer.create' => ['name' => 'Mengajukan transfer gudang', 'module' => 'inventory'],
        'inventory.transfer.approve' => ['name' => 'Menyetujui transfer gudang', 'module' => 'inventory'],
        'inventory.reconcile' => ['name' => 'Melihat rekonsiliasi persediaan', 'module' => 'inventory'],
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
                'master.view', 'catalog.view', 'catalog.manage', 'suppliers.view', 'suppliers.manage',
                'locations.view', 'locations.manage', 'pricing.view', 'pricing.manage', 'tax.view',
                'inventory.view', 'inventory.opening.create', 'inventory.opening.approve',
                'inventory.transfer.create', 'inventory.transfer.approve', 'inventory.reconcile',
            ],
        ],
        'purchasing' => ['name' => 'Purchasing', 'permissions' => [
            'approvals.view', 'approvals.request', 'master.view', 'catalog.view', 'suppliers.view',
            'suppliers.manage', 'pricing.view', 'tax.view',
        ]],
        'inventory' => ['name' => 'Inventory / Gudang', 'permissions' => [
            'approvals.view', 'approvals.request', 'master.view', 'catalog.view', 'locations.view', 'locations.manage',
            'inventory.view', 'inventory.opening.create', 'inventory.transfer.create', 'inventory.reconcile',
        ]],
        'cashier' => ['name' => 'Kasir', 'permissions' => [
            'approvals.request', 'master.view', 'catalog.view', 'pricing.view', 'tax.view',
        ]],
        'finance' => ['name' => 'Finance', 'permissions' => [
            'approvals.view', 'approvals.request', 'master.view', 'catalog.view', 'suppliers.view',
            'pricing.view', 'tax.view', 'tax.manage',
            'inventory.view', 'inventory.reconcile',
        ]],
        'marketing' => ['name' => 'Marketing', 'permissions' => [
            'approvals.view', 'approvals.request', 'master.view', 'catalog.view', 'pricing.view', 'pricing.manage', 'tax.view',
        ]],
    ],
];
