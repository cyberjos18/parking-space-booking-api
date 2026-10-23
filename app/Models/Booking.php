<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Booking
 *
 * @property int $id
 * @property string $reserveId
 * @property Carbon $date_beging_reservation
 * @property Carbon $date_ending_reservation
 * @property int $parking_id
 * @property string $vehicle_registration_number
 *
 * @package App\Models
 */
class Booking extends Model
{
    /**
     * Date format the API speaks, on input and on output.
     * The database stores the usual Y-m-d H:i:s instead.
     */
    const API_DATE_FORMAT = 'd-m-Y H:i:s';

    protected $table = 'bookings';
    public $timestamps = false;

    protected $casts = [
        'id' => 'int',
        'parking_id' => 'int'
    ];

    protected $dates = [
        'date_beging_reservation',
        'date_ending_reservation'
    ];

    protected $fillable = [
        'reserveId',
        'date_beging_reservation',
        'date_ending_reservation',
        'parking_id',
        'vehicle_registration_number'
    ];

    /**
     * Bookings of a parking space whose time range overlaps the given one.
     *
     * Two ranges overlap when each one starts before the other ends. The
     * comparison is strict, so a booking that starts exactly when another
     * one ends is not an overlap and is allowed.
     *
     * @param  string  $begin  Database format (Y-m-d H:i:s)
     * @param  string  $end    Database format (Y-m-d H:i:s)
     */
    public function scopeOverlapping(Builder $query, $parkingId, $begin, $end)
    {
        return $query->where('parking_id', '=', $parkingId)
            ->where('date_beging_reservation', '<', $end)
            ->where('date_ending_reservation', '>', $begin);
    }

    /**
     * Turn a date coming from the API into the format the database expects.
     *
     * Returns null when the input does not match the API format so the
     * caller can report it as a validation error. Carbon throws on a
     * malformed date, and letting that exception escape would answer a bad
     * request with a 500 instead of a 422.
     *
     * @return string|null
     */
    public static function toDatabaseDate($date)
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat(self::API_DATE_FORMAT, $date)->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Turn a date stored in the database into the format the API returns.
     *
     * @return string
     */
    public static function toApiDate($date)
    {
        return Carbon::parse($date)->format(self::API_DATE_FORMAT);
    }
}
