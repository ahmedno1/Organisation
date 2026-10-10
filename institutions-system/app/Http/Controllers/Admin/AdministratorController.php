<?php

namespace App\Http\Controllers\Admin;

use App\Models\Administrator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdministratorController extends Controller
{
    public function index()
    {
        $administrators = Administrator::all();
        return view('admin.administrators.index', compact('administrators'));
    }

    public function create()
    {
        return view('admin.administrators.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:administrators',
        ]);

        Administrator::create($request->all());
        return redirect()->route('administrators.index')->with('success', 'Administrator created successfully.');
    }

    public function edit(Administrator $administrator)
    {
        return view('admin.administrators.edit', compact('administrator'));
    }

    public function update(Request $request, Administrator $administrator)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:administrators,email,' . $administrator->id,
        ]);

        $administrator->update($request->all());
        return redirect()->route('administrators.index')->with('success', 'Administrator updated successfully.');
    }

    public function destroy(Administrator $administrator)
    {
        $administrator->delete();
        return redirect()->route('administrators.index')->with('success', 'Administrator deleted successfully.');
    }
}