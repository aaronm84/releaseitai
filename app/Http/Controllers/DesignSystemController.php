<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DesignSystemController extends Controller
{
    public function index()
    {
        return Inertia::render('DesignSystem/Index');
    }
}