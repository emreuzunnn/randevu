<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Contracts\View\View;

class PublicTicketHistoryController extends Controller
{
    public function show(Appointment $appointment, string $token): View
    {
        abort_unless(hash_equals((string) $appointment->public_token, $token), 404);

        $phoneNumber = preg_replace('/\D+/', '', (string) $appointment->phone_number);
        $phoneCode = preg_replace('/\D+/', '', (string) $appointment->phone_country_code);

        $appointments = Appointment::query()
            ->with(['studio.company', 'assignedArtist'])
            ->where(function ($query) use ($phoneNumber, $phoneCode): void {
                $query->whereRaw("replace(replace(replace(phone_number, ' ', ''), '-', ''), '+', '') = ?", [$phoneNumber]);

                if ($phoneCode !== '') {
                    $query->whereRaw("replace(replace(phone_country_code, '+', ''), ' ', '') = ?", [$phoneCode]);
                }
            })
            ->latest('appointment_at')
            ->limit(30)
            ->get();

        return view('public.ticket-history', [
            'appointment' => $appointment,
            'appointments' => $appointments,
        ]);
    }
}
