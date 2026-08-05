<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorklistItem;

class WorklistController extends Controller
{
    public function index()
    {
        return WorklistItem::with('order.patient')->orderBy('scheduled_at')->get();
    }
}
