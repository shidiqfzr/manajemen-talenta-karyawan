<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Employee; // Keep this so it can extend properly

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::all();

        return view('user.employees.index', compact('employees'));
    }

    public function show($nik)
    {
        $employee = Employee::findOrFail($nik);

        return view('user.employees.show', compact('employee'));
    }
}
