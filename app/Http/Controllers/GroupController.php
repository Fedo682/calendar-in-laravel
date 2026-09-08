<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Group;

class GroupController extends Controller
{
    public function index()
    {
        $groups = auth()->user()->groups()->get();

        return Inertia::render('Groups/Index', [
            'groups' => $groups,
        ]);
    }

    public function create()
    {
        return Inertia::render('Groups/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();

        Group::create($validated);

        return redirect()->route('groups.index')->with('success', 'Group created successfully!');
    }
}
