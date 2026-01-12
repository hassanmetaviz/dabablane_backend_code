<?php

namespace App\Http\Resources\Front\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BlaneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get original values from database for the date fields to avoid timezone conversion issues
        $originalDates = DB::table('blanes')
            ->where('id', $this->id)
            ->select(['start_date', 'expiration_date', 'heure_debut', 'heure_fin'])
            ->first();

        $data = parent::toArray($request);

        // Replace the transformed date fields with original database values formatted as date-only
        if ($originalDates) {
            // Format date fields as Y-m-d to avoid timezone issues
            if ($originalDates->start_date) {
                $data['start_date'] = Carbon::parse($originalDates->start_date)->format('Y-m-d');
            }
            if ($originalDates->expiration_date) {
                $data['expiration_date'] = Carbon::parse($originalDates->expiration_date)->format('Y-m-d');
            }
            // Keep datetime fields as-is from database
            if ($originalDates->heure_debut) {
                $data['heure_debut'] = $originalDates->heure_debut;
            }
            if ($originalDates->heure_fin) {
                $data['heure_fin'] = $originalDates->heure_fin;
            }
        }

        // Only include share_token for authenticated users
        if (!Auth::check()) {
            unset($data['share_token']);
        }

        // Always include share_url with different formats based on visibility
        if (isset($data['visibility'])) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            switch ($data['visibility']) {
                case 'link':
                    // For link visibility, include share_url only if authenticated or token is provided
                    if (Auth::check() && isset($data['share_token'])) {
                        $data['share_url'] = "{$frontendUrl}/blane/{$this->slug}/{$data['share_token']}";
                    } elseif ($request->has('token') && $request->input('token') === $this->share_token) {
                        $data['share_url'] = "{$frontendUrl}/blane/{$this->slug}/{$this->share_token}";
                    } else {
                        $data['share_url'] = null;
                    }
                    break;

                case 'public':
                    // Direct URL for public visibility
                    $data['share_url'] = "{$frontendUrl}/blane/{$this->slug}";
                    break;

                case 'private':
                default:
                    // No shareable URL for private visibility
                    $data['share_url'] = null;
                    break;
            }
        }

        return $data;
    }
}
