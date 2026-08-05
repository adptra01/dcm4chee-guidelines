<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index()
    {
        return Patient::orderBy('id')->get();
    }

    public function store(Request $request)
    {
        return Patient::create($request->validate([
            'patient_id' => 'required|string|unique:patients',
            'name' => 'required|string',
            'sex' => 'nullable|in:M,F,O',
            'birthdate' => 'nullable|date',
        ]));
    }
}
