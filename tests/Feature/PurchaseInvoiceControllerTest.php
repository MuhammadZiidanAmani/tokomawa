<?php

namespace Tests\Feature;

use Tests\TestCase;

class PurchaseInvoiceControllerTest extends TestCase
{
    public function test_invoice_and_ap_routes_require_authentication(): void
    {
        $this->get('/pembelian/faktur-pembelian')->assertRedirect('/login');
        $this->get('/keuangan/hutang-supplier')->assertRedirect('/login');
    }
}
