<?php
/**
 * Created by PhpStorm.
 * User: Jose
 * Date: 30/10/2022
 * Time: 10:15 AM
 */

namespace App\Clases\Booking\Persistencia;


use Illuminate\Support\Facades\Log;
use App\Models\Booking;

/**
 * Database access for bookings.
 *
 * The controller never touches Eloquent: it asks this class for a booking
 * and gets back plain arrays already formatted for the API, or null when
 * there is nothing to return.
 */
class BookingPer
{
    /**
     * Store a new booking.
     *
     * @return string|null  An error message, or null when it was stored.
     */
    public function create($request)
    {
        /*Save booking information in Booking model*/
        try
        {
            $booking = new Booking();
            $booking->reserveId = $request->reserveId;
            $booking->date_beging_reservation = Booking::toDatabaseDate($request->date_beging_reservation);
            $booking->date_ending_reservation = Booking::toDatabaseDate($request->date_ending_reservation);
            $booking->parking_id = $request->parking_id;
            $booking->vehicle_registration_number = $request->vehicle_registration_number;
            $booking->save();

            return null;

        } catch (\Exception $e)
        {
            Log::info('Error in Create Booking: '.$e->getMessage());
            return "Exist a error in Create Booking, please check the log files";
        }
    }

    /**
     * One booking by its reserve identifier.
     *
     * @return array|null  Null when no booking carries that identifier.
     */
    public function findByReserveId($reserveId)
    {
        $booking = Booking::where('reserveId', '=', $reserveId)->first();

        return $booking === null ? null : $this->toArray($booking);
    }

    /**
     * Every booking, narrowed by the optional filters parking_id, from and to.
     *
     * @param  array  $filters  Dates in database format (Y-m-d H:i:s)
     * @return array
     */
    public function search(array $filters = [])
    {
        $query = Booking::query();

        if (! empty($filters['parking_id']))
        {
            $query->where('parking_id', '=', $filters['parking_id']);
        }

        if (! empty($filters['from']))
        {
            $query->where('date_ending_reservation', '>=', $filters['from']);
        }

        if (! empty($filters['to']))
        {
            $query->where('date_beging_reservation', '<=', $filters['to']);
        }

        $bookings = $query->orderBy('date_beging_reservation')->get();

        $result = [];

        foreach ($bookings as $booking)
        {
            $result[] = $this->toArray($booking);
        }

        return $result;
    }

    /**
     * Remove a booking.
     *
     * @return bool  False when no booking carries that identifier.
     */
    public function deleteByReserveId($reserveId)
    {
        $booking = Booking::where('reserveId', '=', $reserveId)->first();

        if ($booking === null)
        {
            return false;
        }

        $booking->delete();

        return true;
    }

    /**
     * Whether the parking space is already taken for any part of the range.
     *
     * @param  string  $begin  Database format (Y-m-d H:i:s)
     * @param  string  $end    Database format (Y-m-d H:i:s)
     * @return bool
     */
    public function hasOverlap($parkingId, $begin, $end)
    {
        return Booking::overlapping($parkingId, $begin, $end)->exists();
    }

    /**
     * A booking as the API returns it, with the dates in the API format.
     *
     * @return array
     */
    private function toArray(Booking $booking)
    {
        return [
            'reserveId' => $booking->reserveId,
            'date_beging_reservation' => Booking::toApiDate($booking->date_beging_reservation),
            'date_ending_reservation' => Booking::toApiDate($booking->date_ending_reservation),
            'parking_id' => $booking->parking_id,
            'vehicle_registration_number' => $booking->vehicle_registration_number,
        ];
    }
}
