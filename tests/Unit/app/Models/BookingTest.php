<?php

namespace Tests\Unit\app\Models;

use App\Models\Booking;
use Tests\TestCase;

/**
 * The model translates between the two date formats the application uses:
 * d-m-Y H:i:s on the API and Y-m-d H:i:s in the database.
 *
 * The conversion towards the database has to be total: Carbon throws on a
 * malformed date, and an exception escaping here answers a bad request with
 * a 500 instead of letting the validator report a 422.
 */
class BookingTest extends TestCase
{
    public function testToDatabaseDateConvertsFromTheApiFormat()
    {
        $this->assertSame(
            '2022-10-30 13:49:00',
            Booking::toDatabaseDate('30-10-2022 13:49:00')
        );
    }

    /**
     * @dataProvider unconvertibleDates
     */
    public function testToDatabaseDateReturnsNullForWhatItCannotConvert($value)
    {
        $this->assertNull(Booking::toDatabaseDate($value));
    }

    /**
     * @return array
     */
    public function unconvertibleDates()
    {
        return [
            'plain text' => ['not-a-date'],
            'database format' => ['2022-10-30 13:49:00'],
            'date without time' => ['30-10-2022'],
            'empty string' => [''],
            'null' => [null],
            'number' => [20221030],
        ];
    }

    public function testToApiDateFormatsWhatTheDatabaseStores()
    {
        $this->assertSame(
            '30-10-2022 13:49:00',
            Booking::toApiDate('2022-10-30 13:49:00')
        );
    }

    public function testBothConversionsAreInverseOfEachOther()
    {
        $apiDate = '30-10-2022 13:49:00';

        $this->assertSame(
            $apiDate,
            Booking::toApiDate(Booking::toDatabaseDate($apiDate))
        );
    }
}
