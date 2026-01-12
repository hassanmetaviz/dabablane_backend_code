<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\ContactResource;

class ContactController extends Controller
{
    /**
     * Display a listing of the Contact.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        $query = Contact::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);


        $paginationSize = $request->input('paginationSize', 10);
        $contact = $query->paginate($paginationSize);

        return ContactResource::collection($contact);
    }

    /**
     * Display the specified contact.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse|ContactResource
     */
    public function show($id, Request $request)
    {

        $query = Contact::query();
        $contact = $query->find($id);

        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        return new ContactResource($contact);
    }

    /**
     * Store a newly created contact.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'fullName' => 'required|string|max:255',
                'email' => 'required|string|max:255',
                'phone' => 'nullble|string|max:255',
                'subject' => 'required|string|max:255',
                'type' => 'required|string|in:client,commercant',
                'message' => 'required|string',
                'status' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $contact = Contact::create($validatedData);
            return response()->json([
                'message' => 'Contact created successfully',
                'data' => new ContactResource($contact),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified contact.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'fullName' => 'required|string|max:255',
                'email' => 'required|string|max:255',
                'phone' => 'nullable|string|max:255',
                'type' => 'required|string|in:client,commercant',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
                'status' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $contact = Contact::find($id);

        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        try {
            $contact->update($validatedData);
            return response()->json([
                'message' => 'contact updated successfully',
                'data' => new ContactResource($contact),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified contact.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $contact = Contact::find($id);

        if (!$contact) {
            return response()->json(['message' => 'contact not found'], 404);
        }

        try {
            $contact->delete();
            return response()->json([
                'message' => 'contact deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply filters to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applyFilters(Request $request, $query)
    {
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
    }

    /**
     * Apply search to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applySearch(Request $request, $query)
    {
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('fullName', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('subject', 'like', "%$search%")
                    ->orWhere('message', 'like', "%$search%");
            });
        }
    }

    /**
     * Apply sorting to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applySorting(Request $request, $query)
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortBy = ['created_at', 'fullName', 'email', 'status', 'subject'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
