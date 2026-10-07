<?php

namespace App\Http\Controllers;

class DataController extends Controller
{
    public function rkp()
    {
        return view('data.rkp');
    }

    public function bzting()
    {
        return view('data.bzting');
    }

    public function pelatihan()
    {
        return view('data.pelatihan');
    }

    public function ckp()
    {
        return view('data.ckp');
    }

    public function manajemenTalenta()
    {
        return view('data.manajemen-talenta');
    }
}
