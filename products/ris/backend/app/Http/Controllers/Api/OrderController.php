<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorklistItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return Order::with('patient')->orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $order = Order::create($request->validate([
            'order_no' => 'required|string|unique:orders',
            'patient_id' => 'required|exists:patients,id',
            'modality' => 'required|string|max:8',
            'requested_at' => 'nullable|date',
        ]));
        // Setiap order baru langsung punya slot worklist (MWL source)
        WorklistItem::create([
            'order_id' => $order->id,
            'scheduled_aet' => $request->input('scheduled_aet', 'RIS'),
            'scheduled_at' => $request->input('scheduled_at'),
        ]);
        return $order->load('patient', 'worklistItem');
    }

    public function updateStatus(Request $request, int $id)
    {
        // Update status order + sinkron worklist item (dari MPPS / workflow)
        $order = Order::findOrFail($id);
        $order->update($request->validate([
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'study_instance_uid' => 'nullable|string|max:64',
        ]));
        WorklistItem::where('order_id', $order->id)->update([
            'status' => $order->status === 'completed' ? 'completed' : 'scheduled',
        ]);
        return $order->load('patient', 'worklistItem');
    }
}
