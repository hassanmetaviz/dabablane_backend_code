<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Dabablane API",
 *     version="1.0.0",
 *     description="Multi-vendor marketplace API for orders, reservations, and vendor management. This API supports both web and mobile applications.",
 *     @OA\Contact(
 *         email="support@dabablane.com",
 *         name="Dabablane Support"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://dabablane.com/terms"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token in the format: Bearer {token}"
 * )
 *
 * @OA\Tag(name="Authentication", description="User authentication endpoints")
 * @OA\Tag(name="Vendor Authentication", description="Vendor authentication endpoints")
 * @OA\Tag(name="Categories", description="Category management")
 * @OA\Tag(name="Blanes", description="Blane (product/service) management")
 * @OA\Tag(name="Orders", description="Order management")
 * @OA\Tag(name="Reservations", description="Reservation management")
 * @OA\Tag(name="Addresses", description="User address management")
 * @OA\Tag(name="Cities", description="City listings")
 * @OA\Tag(name="Coupons", description="Coupon management")
 * @OA\Tag(name="Ratings", description="Rating and review management")
 * @OA\Tag(name="Banners", description="Banner management")
 * @OA\Tag(name="FAQs", description="Frequently asked questions")
 * @OA\Tag(name="Contacts", description="Contact form submissions")
 * @OA\Tag(name="Subscriptions", description="Vendor subscription management")
 * @OA\Tag(name="Vendors", description="Vendor management")
 * @OA\Tag(name="Customers", description="Customer management")
 * @OA\Tag(name="Analytics", description="Analytics and reporting")
 *
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="message", type="string", example="Success"),
 *     @OA\Property(property="data", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="code", type="integer", example=400),
 *     @OA\Property(property="message", type="string", example="Error message"),
 *     @OA\Property(property="errors", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="code", type="integer", example=422),
 *     @OA\Property(property="message", type="string", example="Validation error"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(type="string")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="NotFoundResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="code", type="integer", example=404),
 *     @OA\Property(property="message", type="string", example="Resource not found")
 * )
 *
 * @OA\Schema(
 *     schema="UnauthorizedResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="code", type="integer", example=401),
 *     @OA\Property(property="message", type="string", example="Unauthenticated")
 * )
 *
 * @OA\Schema(
 *     schema="ForbiddenResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="code", type="integer", example=403),
 *     @OA\Property(property="message", type="string", example="Access denied")
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="from", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=10),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="to", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=150)
 * )
 *
 * @OA\Schema(
 *     schema="PaginationLinks",
 *     type="object",
 *     @OA\Property(property="first", type="string", example="http://api.example.com/items?page=1"),
 *     @OA\Property(property="last", type="string", example="http://api.example.com/items?page=10"),
 *     @OA\Property(property="prev", type="string", nullable=true),
 *     @OA\Property(property="next", type="string", example="http://api.example.com/items?page=2")
 * )
 *
 * @OA\PathItem(path="/api")
 */
abstract class Controller
{
    use AuthorizesRequests;
}
