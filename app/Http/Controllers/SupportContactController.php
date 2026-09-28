<?php

namespace App\Http\Controllers;

use App\Models\SupportContact;
use Illuminate\Http\Request;

class SupportContactController extends Controller
{
    public function index()
    {
        $supportContacts = SupportContact::orderBy('category')->orderBy('vendor_name')->get();

        return view('support_contacts.index', compact('supportContacts'));
    }

    public function create()
    {
        $categories = SupportContact::orderBy('category')->pluck('category')->unique();

        return view('support_contacts.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category'        => 'required|string|max:100',
            'vendor_name'     => 'required|string|max:100|unique:support_contacts,vendor_name,NULL,id,category,' . $request->category,
            'contact_person'  => 'nullable|string|max:255',
            'phone'           => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'remarks'         => 'nullable|string',
        ], [
            'vendor_name.unique' => 'This vendor already has a contact recorded under this category.',
        ]);

        SupportContact::create($validated);

        return redirect()
            ->route('support-contacts.index')
            ->with('success', 'Support Contact Added Successfully');
    }

    public function edit(SupportContact $supportContact)
    {
        $categories = SupportContact::orderBy('category')->pluck('category')->unique();

        return view('support_contacts.edit', compact('supportContact', 'categories'));
    }

    public function update(Request $request, SupportContact $supportContact)
    {
        $validated = $request->validate([
            'category'        => 'required|string|max:100',
            'vendor_name'     => 'required|string|max:100|unique:support_contacts,vendor_name,' . $supportContact->id . ',id,category,' . $request->category,
            'contact_person'  => 'nullable|string|max:255',
            'phone'           => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'remarks'         => 'nullable|string',
        ], [
            'vendor_name.unique' => 'This vendor already has a contact recorded under this category.',
        ]);

        $supportContact->update($validated);

        return redirect()
            ->route('support-contacts.index')
            ->with('success', 'Support Contact Updated Successfully');
    }

    public function destroy(SupportContact $supportContact)
    {
        $supportContact->delete();

        return redirect()
            ->route('support-contacts.index')
            ->with('success', 'Support Contact Deleted Successfully');
    }
}
