<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Filter per order: GET /api/reports?order_id=1
        return Report::with('order.patient')
            ->when($request->integer('order_id'), fn ($q, $oid) => $q->where('order_id', $oid))
            ->orderBy('id')->get();
    }

    public function store(Request $request)
    {
        return Report::create($request->validate([
            'order_id' => 'required|exists:orders,id',
            'radiologist' => 'nullable|string|max:100',
            'findings' => 'nullable|string',
            'impression' => 'nullable|string',
            'status' => 'nullable|in:draft,final,amended',
        ]))->load('order.patient');
    }
}
