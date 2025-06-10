<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Models\Training;
use App\Http\Controllers\Controller;

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
