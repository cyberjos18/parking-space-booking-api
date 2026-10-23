<?php

namespace Tests\Feature\app\Http\Controllers\Bookings;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Every test owns the rows it needs and rolls them back when it finishes,
 * so the suite gives the same result however many times it runs and in
 * whatever order the tests happen to execute.
 *
 * The parking identifiers are high on purpose, to stay clear of whatever
 * the imported fixtures already occupy.
 */
class BookingsControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array
     */
    private function payload(array $overrides = [])
    {
        return array_merge([
            'reserveId' => 'T-BASE',
            'date_beging_reservation' => '30-10-2022 13:00:00',
            'date_ending_reservation' => '30-10-2022 18:00:00',
            'parking_id' => 901,
            'vehicle_registration_number' => '6358YUT',
        ], $overrides);
    }

    /* ----------------------------- POST ----------------------------- */

    public function testStoreCreatesTheBookingAndEchoesTheReserveId()
    {
        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-NEW',
            'parking_id' => 910,
        ]));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok', 'reserveId' => 'T-NEW']);

        $this->assertDatabaseHas('bookings', [
            'reserveId' => 'T-NEW',
            'parking_id' => 910,
            'date_beging_reservation' => '2022-10-30 13:00:00',
            'date_ending_reservation' => '2022-10-30 18:00:00',
            'vehicle_registration_number' => '6358YUT',
        ]);
    }

    public function testStoreRejectsAReserveIdThatAlreadyExists()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-DUP',
            'parking_id' => 911,
        ]))->assertStatus(200);

        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-DUP',
            'parking_id' => 912,
        ]));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'reserveId' => ['The number of reserve already exist please check again'],
        ]);
    }

    /**
     * A malformed date used to reach Carbon before the validator and blow up
     * with a 500. It has to come back as an ordinary validation error.
     */
    public function testStoreAnswersAMalformedDateWithAValidationErrorAndNotAnException()
    {
        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-BADFMT',
            'date_beging_reservation' => 'not-a-date',
        ]));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'date_beging_reservation' => ['The date beging reservation format must be DD-MM-YYYY H:i:s'],
        ]);
        $this->assertDatabaseMissing('bookings', ['reserveId' => 'T-BADFMT']);
    }

    public function testStoreAnswersAnIsoFormattedDateWithAValidationError()
    {
        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-ISO',
            'date_beging_reservation' => '2022-10-30 13:00:00',
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('bookings', ['reserveId' => 'T-ISO']);
    }

    public function testStoreRejectsAnEndDateEarlierThanTheBeginDate()
    {
        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-REV',
            'date_beging_reservation' => '30-10-2022 18:00:00',
            'date_ending_reservation' => '30-10-2022 13:00:00',
        ]));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'date_ending_reservation' => ['The date ending reservation must be later than the date beging reservation'],
        ]);
        $this->assertDatabaseMissing('bookings', ['reserveId' => 'T-REV']);
    }

    public function testStoreRejectsABookingThatBeginsAndEndsAtTheSameInstant()
    {
        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-ZERO',
            'date_beging_reservation' => '30-10-2022 13:00:00',
            'date_ending_reservation' => '30-10-2022 13:00:00',
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('bookings', ['reserveId' => 'T-ZERO']);
    }

    public function testStoreRejectsASecondBookingOverlappingTheSameParkingSpace()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-OVL-1',
            'parking_id' => 920,
            'date_beging_reservation' => '30-10-2022 10:00:00',
            'date_ending_reservation' => '30-10-2022 14:00:00',
        ]))->assertStatus(200);

        /*Starts inside the range already booked for that space*/
        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-OVL-2',
            'parking_id' => 920,
            'date_beging_reservation' => '30-10-2022 13:00:00',
            'date_ending_reservation' => '30-10-2022 16:00:00',
        ]));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'parking_id' => ['The parking space is already booked for that time range'],
        ]);
        $this->assertDatabaseMissing('bookings', ['reserveId' => 'T-OVL-2']);
    }

    public function testStoreRejectsABookingThatFullyContainsAnExistingOne()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-CNT-1',
            'parking_id' => 921,
            'date_beging_reservation' => '30-10-2022 12:00:00',
            'date_ending_reservation' => '30-10-2022 13:00:00',
        ]))->assertStatus(200);

        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-CNT-2',
            'parking_id' => 921,
            'date_beging_reservation' => '30-10-2022 10:00:00',
            'date_ending_reservation' => '30-10-2022 20:00:00',
        ]));

        $response->assertStatus(422);
    }

    public function testStoreAcceptsTheSameRangeOnADifferentParkingSpace()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-SEP-1',
            'parking_id' => 930,
        ]))->assertStatus(200);

        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-SEP-2',
            'parking_id' => 931,
        ]))->assertStatus(200);

        $this->assertDatabaseHas('bookings', ['reserveId' => 'T-SEP-2']);
    }

    /**
     * The overlap comparison is strict, so one booking may start exactly
     * when the previous one ends.
     */
    public function testStoreAcceptsABookingThatStartsWhenThePreviousOneEnds()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-B2B-1',
            'parking_id' => 940,
            'date_beging_reservation' => '30-10-2022 10:00:00',
            'date_ending_reservation' => '30-10-2022 12:00:00',
        ]))->assertStatus(200);

        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-B2B-2',
            'parking_id' => 940,
            'date_beging_reservation' => '30-10-2022 12:00:00',
            'date_ending_reservation' => '30-10-2022 14:00:00',
        ]));

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', ['reserveId' => 'T-B2B-2']);
    }

    public function testStoreReportsEveryMissingField()
    {
        $response = $this->postJson('api/bookings', []);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'errors' => [
                'reserveId',
                'date_beging_reservation',
                'date_ending_reservation',
                'parking_id',
                'vehicle_registration_number',
            ],
        ]);
    }

    public function testStoreRejectsANonNumericParkingId()
    {
        $response = $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-PKID',
            'parking_id' => 'first-floor',
        ]));

        $response->assertStatus(422);
        $response->assertJsonFragment(['parking_id' => ['The parking ID must be a number']]);
    }

    /* ----------------------------- GET one ----------------------------- */

    public function testShowReturnsTheBookingWithTheDatesInTheApiFormat()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-SHOW',
            'parking_id' => 950,
        ]))->assertStatus(200);

        $response = $this->getJson('api/bookings/T-SHOW');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'bookingresult' => [
                'reserveId' => 'T-SHOW',
                'date_beging_reservation' => '30-10-2022 13:00:00',
                'date_ending_reservation' => '30-10-2022 18:00:00',
                'parking_id' => 950,
                'vehicle_registration_number' => '6358YUT',
            ],
        ]);
    }

    /**
     * A missing booking used to answer 200 with a message inside the body,
     * which made it impossible to tell apart from a hit.
     */
    public function testShowReturnsNotFoundWhenNoBookingCarriesThatReserveId()
    {
        $response = $this->getJson('api/bookings/T-NOPE');

        $response->assertStatus(404);
        $response->assertJsonStructure(['errors' => ['reserveId']]);
    }

    /* ----------------------------- GET list ----------------------------- */

    public function testIndexListsTheBookingsOfOneParkingSpace()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-LST-1',
            'parking_id' => 960,
            'date_beging_reservation' => '30-10-2022 08:00:00',
            'date_ending_reservation' => '30-10-2022 09:00:00',
        ]))->assertStatus(200);

        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-LST-2',
            'parking_id' => 960,
            'date_beging_reservation' => '30-10-2022 10:00:00',
            'date_ending_reservation' => '30-10-2022 11:00:00',
        ]))->assertStatus(200);

        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-LST-3',
            'parking_id' => 961,
        ]))->assertStatus(200);

        $response = $this->getJson('api/bookings?parking_id=960');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok', 'count' => 2]);
        $response->assertJsonFragment(['reserveId' => 'T-LST-1']);
        $response->assertJsonFragment(['reserveId' => 'T-LST-2']);
        $response->assertJsonMissing(['reserveId' => 'T-LST-3']);
    }

    public function testIndexNarrowsTheListToTheRequestedWindow()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-WIN-IN',
            'parking_id' => 970,
            'date_beging_reservation' => '30-10-2022 10:00:00',
            'date_ending_reservation' => '30-10-2022 11:00:00',
        ]))->assertStatus(200);

        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-WIN-OUT',
            'parking_id' => 970,
            'date_beging_reservation' => '31-10-2022 10:00:00',
            'date_ending_reservation' => '31-10-2022 11:00:00',
        ]))->assertStatus(200);

        $response = $this->getJson(
            'api/bookings?parking_id=970&from=30-10-2022 00:00:00&to=30-10-2022 23:59:59'
        );

        $response->assertStatus(200);
        $response->assertJson(['count' => 1]);
        $response->assertJsonFragment(['reserveId' => 'T-WIN-IN']);
        $response->assertJsonMissing(['reserveId' => 'T-WIN-OUT']);
    }

    public function testIndexRejectsAMalformedDateFilter()
    {
        $response = $this->getJson('api/bookings?from=2022-10-30');

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'from' => ['The from filter format must be DD-MM-YYYY H:i:s'],
        ]);
    }

    public function testIndexAcceptsNoFiltersAtAll()
    {
        $response = $this->getJson('api/bookings');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'count', 'bookings']);
    }

    /* ----------------------------- DELETE ----------------------------- */

    public function testDestroyRemovesTheBooking()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-DEL',
            'parking_id' => 980,
        ]))->assertStatus(200);

        $response = $this->deleteJson('api/bookings/T-DEL');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok', 'reserveId' => 'T-DEL']);
        $this->assertDatabaseMissing('bookings', ['reserveId' => 'T-DEL']);
    }

    public function testDestroyReturnsNotFoundWhenNoBookingCarriesThatReserveId()
    {
        $response = $this->deleteJson('api/bookings/T-NOPE');

        $response->assertStatus(404);
    }

    /**
     * Cancelling frees the space, so the same range becomes bookable again.
     */
    public function testDestroyFreesTheSpaceForANewBooking()
    {
        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-FREE-1',
            'parking_id' => 990,
        ]))->assertStatus(200);

        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-FREE-2',
            'parking_id' => 990,
        ]))->assertStatus(422);

        $this->deleteJson('api/bookings/T-FREE-1')->assertStatus(200);

        $this->postJson('api/bookings', $this->payload([
            'reserveId' => 'T-FREE-2',
            'parking_id' => 990,
        ]))->assertStatus(200);
    }
}
