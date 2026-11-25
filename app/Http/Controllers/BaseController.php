<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\View;

abstract class BaseController extends Controller
{
    /**
     * @param  array<string, string>  $crumbs
     */
    protected function breadcrumbs(array $crumbs): void
    {
        View::share('breadcrumbs', $crumbs);
    }
}
