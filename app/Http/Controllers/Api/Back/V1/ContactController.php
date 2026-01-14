<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\ContactResource;

/**
 * @OA\Schema(
 *     schema="BackContact",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="fullName", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="subject", type="string", example="General Inquiry"),
 *     @OA\Property(property="type", type="string", enum={"client", "commercant"}, example="client"),
 *     @OA\Property(property="message", type="string", example="I have a question about..."),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ContactController extends BaseController
{
    /**
     * Display a listing of the Contact.
     *
     * @OA\Get(
     *     path="/back/v1/contacts",
     *     tags={"Back - Contacts"},
     *     summary="List all contacts",
     *     operationId="backContactsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="email", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Contacts retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackContact")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
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
     * @OA\Get(
     *     path="/back/v1/contacts/{id}",
     *     tags={"Back - Contacts"},
     *     summary="Get a specific contact",
     *     operationId="backContactsShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Contact retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackContact"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
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
     * @OA\Post(
     *     path="/back/v1/contacts",
     *     tags={"Back - Contacts"},
     *     summary="Create a new contact",
     *     operationId="backContactsStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"fullName", "email", "subject", "type", "message", "status"},
     *         @OA\Property(property="fullName", type="string", maxLength=255, example="John Doe"),
     *         @OA\Property(property="email", type="string", maxLength=255, example="john@example.com"),
     *         @OA\Property(property="phone", type="string", maxLength=255),
     *         @OA\Property(property="subject", type="string", maxLength=255, example="General Inquiry"),
     *         @OA\Property(property="type", type="string", enum={"client", "commercant"}, example="client"),
     *         @OA\Property(property="message", type="string", example="I have a question..."),
     *         @OA\Property(property="status", type="string", example="pending")
     *     )),
     *     @OA\Response(response=201, description="Contact created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackContact"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
            ], 500);
        }
    }

    /**
     * Update the specified contact.
     *
     * @OA\Put(
     *     path="/back/v1/contacts/{id}",
     *     tags={"Back - Contacts"},
     *     summary="Update a contact",
     *     operationId="backContactsUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"fullName", "email", "type", "subject", "message", "status"},
     *         @OA\Property(property="fullName", type="string", maxLength=255),
     *         @OA\Property(property="email", type="string", maxLength=255),
     *         @OA\Property(property="phone", type="string", maxLength=255),
     *         @OA\Property(property="type", type="string", enum={"client", "commercant"}),
     *         @OA\Property(property="subject", type="string", maxLength=255),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="status", type="string")
     *     )),
     *     @OA\Response(response=200, description="Contact updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackContact"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
            ], 500);
        }
    }

    /**
     * Remove the specified contact.
     *
     * @OA\Delete(
     *     path="/back/v1/contacts/{id}",
     *     tags={"Back - Contacts"},
     *     summary="Delete a contact",
     *     operationId="backContactsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Contact deleted"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
