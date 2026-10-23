<?php

namespace App\Http\Controllers\Bookings;

use Illuminate\Http\Request;
use App\Clases\Booking\Persistencia\BookingPer;
use App\Clases\Booking\Validations\BookingValidations;
use App\Http\Controllers\Controller;
use App\Models\Booking;


/**
 * Booking endpoints.
 *
 * The controller handles HTTP and nothing else: it hands the request to the
 * validation layer, asks the persistence layer for the data and maps the
 * outcome to a status code. Both collaborators are injected, so a test can
 * replace either one.
 */
class BookingsController extends Controller
{
    /** @var BookingValidations */
    private $validations;

    /** @var BookingPer */
    private $bookings;

    public function __construct(BookingValidations $validations, BookingPer $bookings)
    {
        $this->validations = $validations;
        $this->bookings = $bookings;
    }

    /**
     * POST /api/bookings
     */
    public function store(Request $request)
    {
        /*Apply validations*/
        $errors = $this->validations->validateCreate($request);

        if ($errors)
        {
            return $this->errorResponse($errors, 422);
        }

        /*The parking space must be free for the whole range*/
        $begin = Booking::toDatabaseDate($request->date_beging_reservation);
        $end = Booking::toDatabaseDate($request->date_ending_reservation);

        $errors = $this->validations->validateAvailability(
            $this->bookings,
            $request->parking_id,
            $begin,
            $end
        );

        if ($errors)
        {
            return $this->errorResponse($errors, 422);
        }

        /*Creating booking in database*/
        $error = $this->bookings->create($request);

        if ($error !== null)
        {
            return $this->errorResponse($error, 422);
        }

        return response()->json([
            'status' => 'ok',
            'reserveId' => $request->reserveId,
        ], 200);
    }

    /**
     * GET /api/bookings/{reserveId}
     */
    public function show($reserveId)
    {
        /*Apply validations*/
        $errors = $this->validations->validateReserveId($reserveId);

        if ($errors)
        {
            return $this->errorResponse($errors, 422);
        }

        $booking = $this->bookings->findByReserveId($reserveId);

        if ($booking === null)
        {
            return $this->notFoundResponse();
        }

        return response()->json([
            'status' => 'ok',
            'bookingresult' => $booking,
        ], 200);
    }

    /**
     * GET /api/bookings
     *
     * Optional filters: parking_id, from and to. A booking matches the date
     * filters when it is active at some point inside the requested window.
     */
    public function index(Request $request)
    {
        /*Apply validations*/
        $errors = $this->validations->validateFilters($request);

        if ($errors)
        {
            return $this->errorResponse($errors, 422);
        }

        $bookings = $this->bookings->search([
            'parking_id' => $request->parking_id,
            'from' => Booking::toDatabaseDate($request->from),
            'to' => Booking::toDatabaseDate($request->to),
        ]);

        return response()->json([
            'status' => 'ok',
            'count' => count($bookings),
            'bookings' => $bookings,
        ], 200);
    }

    /**
     * DELETE /api/bookings/{reserveId}
     */
    public function destroy($reserveId)
    {
        /*Apply validations*/
        $errors = $this->validations->validateReserveId($reserveId);

        if ($errors)
        {
            return $this->errorResponse($errors, 422);
        }

        if (! $this->bookings->deleteByReserveId($reserveId))
        {
            return $this->notFoundResponse();
        }

        return response()->json([
            'status' => 'ok',
            'reserveId' => $reserveId,
        ], 200);
    }

    private function errorResponse($errors, $status)
    {
        return response()->json(['errors' => $errors], $status);
    }

    private function notFoundResponse()
    {
        return response()->json([
            'errors' => [
                'reserveId' => ["Don't exist a booking with this reserve number"],
            ],
        ], 404);
    }
}
