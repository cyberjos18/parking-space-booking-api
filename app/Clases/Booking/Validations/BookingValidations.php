<?php
/**
 * Created by PhpStorm.
 * User: Jose
 * Date: 30/10/2022
 * Time: 10:20 AM
 */

namespace App\Clases\Booking\Validations;


use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use App\Clases\Booking\Persistencia\BookingPer;
use App\Models\Booking;

/**
 * Validation rules for the booking endpoints.
 *
 * Every method returns a MessageBag when the request is invalid and null
 * when it is acceptable, so the controller only has to decide the status
 * code. Dates are validated in the format the API documents (d-m-Y H:i:s)
 * and converted to the database format afterwards, never before: converting
 * first turns a malformed date into an exception instead of a 422.
 */
class BookingValidations
{
    /**
     * Payload of a new booking.
     *
     * @return MessageBag|null
     */
    public function validateCreate($request)
    {
        $data = [
            'reserveId' => $request->reserveId,
            'date_beging_reservation' => $request->date_beging_reservation,
            'date_ending_reservation' => $request->date_ending_reservation,
            'parking_id' => $request->parking_id,
            'vehicle_registration_number' => $request->vehicle_registration_number,
        ];

        $rules = [
            'reserveId' => 'required|max:15|unique:bookings,reserveId',
            'date_beging_reservation' => 'required|date_format:' . Booking::API_DATE_FORMAT,
            'date_ending_reservation' => 'required|date_format:' . Booking::API_DATE_FORMAT
                . '|after:date_beging_reservation',
            'parking_id' => 'required|integer',
            'vehicle_registration_number' => 'required|max:15',
        ];

        $messages = [
            'reserveId.required' => 'The number of reserve is required',
            'reserveId.max' => 'The number of reserve cannot be longer than 15 characters',
            'reserveId.unique' => 'The number of reserve already exist please check again',
            'date_beging_reservation.required' => 'The date beging reservation is required',
            'date_beging_reservation.date_format' => 'The date beging reservation format must be DD-MM-YYYY H:i:s',
            'date_ending_reservation.required' => 'The date ending reservation is required',
            'date_ending_reservation.date_format' => 'The date ending reservation format must be DD-MM-YYYY H:i:s',
            'date_ending_reservation.after' => 'The date ending reservation must be later than the date beging reservation',
            'parking_id.required' => 'The parking ID is required',
            'parking_id.integer' => 'The parking ID must be a number',
            'vehicle_registration_number.required' => 'The vehicle registration number is required',
            'vehicle_registration_number.max' => 'The vehicle registration number cannot be longer than 15 characters',
        ];

        return $this->errorsOf($data, $rules, $messages);
    }

    /**
     * Reserve identifier taken from the URL.
     *
     * @return MessageBag|null
     */
    public function validateReserveId($reserveId)
    {
        return $this->errorsOf(
            ['reserveId' => $reserveId],
            ['reserveId' => 'required|max:15'],
            [
                'reserveId.required' => 'The number of reserve is required',
                'reserveId.max' => 'The number of reserve cannot be longer than 15 characters',
            ]
        );
    }

    /**
     * Optional filters of the booking list. Absent filters are valid.
     *
     * @return MessageBag|null
     */
    public function validateFilters($request)
    {
        $data = [
            'parking_id' => $request->parking_id,
            'from' => $request->from,
            'to' => $request->to,
        ];

        $rules = [
            'parking_id' => 'nullable|integer',
            'from' => 'nullable|date_format:' . Booking::API_DATE_FORMAT,
            'to' => 'nullable|date_format:' . Booking::API_DATE_FORMAT . '|after_or_equal:from',
        ];

        $messages = [
            'parking_id.integer' => 'The parking ID must be a number',
            'from.date_format' => 'The from filter format must be DD-MM-YYYY H:i:s',
            'to.date_format' => 'The to filter format must be DD-MM-YYYY H:i:s',
            'to.after_or_equal' => 'The to filter cannot be earlier than the from filter',
        ];

        return $this->errorsOf($data, $rules, $messages);
    }

    /**
     * The parking space must be free for the whole requested range.
     *
     * The rule lives here, next to the other rules, while the query that
     * answers it belongs to the persistence layer.
     *
     * @param  string  $begin  Database format (Y-m-d H:i:s)
     * @param  string  $end    Database format (Y-m-d H:i:s)
     * @return MessageBag|null
     */
    public function validateAvailability(BookingPer $bookings, $parkingId, $begin, $end)
    {
        if (! $bookings->hasOverlap($parkingId, $begin, $end)) {
            return null;
        }

        return new MessageBag([
            'parking_id' => ['The parking space is already booked for that time range'],
        ]);
    }

    /**
     * @return MessageBag|null
     */
    private function errorsOf(array $data, array $rules, array $messages)
    {
        $validator = Validator::make($data, $rules, $messages);

        return $validator->fails() ? $validator->errors() : null;
    }
}
