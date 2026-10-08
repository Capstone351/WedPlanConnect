<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'supplierCount' => Supplier::count(),
            'categoryCount' => Supplier::distinct()->count('category'),
        ]);
    }
}
