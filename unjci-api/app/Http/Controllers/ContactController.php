<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPermission('view_contact_requests'), 403, 'Accès non autorisé.');

        // Admin only
        $perPage = $request->query('perPage', 10);
        $contacts = Contact::orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($contacts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fullName' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'consent' => 'required|boolean',
        ]);

        $contact = Contact::create([
            'full_name' => $validated['fullName'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'consent' => $validated['consent'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre message a bien été envoyé.',
            'data' => $contact
        ], 201);
    }
}
