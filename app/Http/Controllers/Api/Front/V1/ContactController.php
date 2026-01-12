<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Controller;
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

class ContactController extends Controller
{
    /**
     * Store a newly created contact message from guest users.
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
                    'error' => $e->getMessage(),
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
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending your message',
                'debug_message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
