<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\Back\V1\ContactResource;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormSubmission;
use App\Notifications\ContactFormNotification;
use App\Models\User;

/**
 * @OA\Schema(
 *     schema="Contact",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="fullName", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="type", type="string", enum={"client", "commercant"}, example="client"),
 *     @OA\Property(property="subject", type="string", example="General Inquiry"),
 *     @OA\Property(property="message", type="string", example="I would like to know more about..."),
 *     @OA\Property(property="status", type="string", enum={"pending", "responded", "closed"}, example="pending"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ContactController extends BaseController
{
    /**
     * Store a newly created contact message from guest users.
     *
     * @OA\Post(
     *     path="/front/v1/contact",
     *     tags={"Contact"},
     *     summary="Submit contact form",
     *     description="Submit a contact form message from guest users",
     *     operationId="submitContact",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fullName", "email", "subject", "message"},
     *             @OA\Property(property="fullName", type="string", maxLength=255, example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, example="john@example.com"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+212612345678"),
     *             @OA\Property(property="type", type="string", enum={"client", "commercant"}, example="client"),
     *             @OA\Property(property="subject", type="string", maxLength=255, example="General Inquiry"),
     *             @OA\Property(property="message", type="string", maxLength=1000, example="I would like to know more about your services...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Contact message submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Thank you for your message. We will contact you soon."),
     *             @OA\Property(property="data", ref="#/components/schemas/Contact")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
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
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:20',
                'type' => 'nullable|string|in:client,commercant',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:1000',
            ]);

            $validatedData['status'] = 'pending';

            Log::info('Attempting to create contact with data:', $validatedData);

            $contact = Contact::create($validatedData);

            try {
                Mail::to('contact@dabablane.com')
                    ->send(new ContactFormSubmission($contact));

                $admins = User::role('admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new ContactFormNotification($contact));
                }
            } catch (\Exception $e) {

                Log::error('Failed to send contact form notification:', [
                    'contact_id' => $contact->id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your message. We will contact you soon.',
                'data' => new ContactResource($contact)
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Validation error in contact form:', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in contact form submission:', [
                'message' => 'An unexpected error occurred.',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending your message',
                'debug_message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
