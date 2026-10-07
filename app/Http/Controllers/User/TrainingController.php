<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Training;

class TrainingController extends Controller
{
    public function index()
    {
        // Get all training records, you can use pagination for better performance
        $trainings = Training::all();

        // Return the view with the training data
        return view('user.trainings.index', compact('trainings'));
    }

    public function show($id)
    {
        $training = Training::with('employees')->findOrFail($id);

        return view('user.trainings.show', compact('training'));
    }
}
