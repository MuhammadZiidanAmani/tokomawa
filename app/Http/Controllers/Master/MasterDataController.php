<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('master.view');

        return view('master.index');
    }
}
