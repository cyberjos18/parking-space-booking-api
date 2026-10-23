<?php

namespace Tests\Unit\app\Clases\Booking\Validations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use App\Clases\Booking\Persistencia\BookingPer;
use App\Clases\Booking\Validations\BookingValidations;
use Tests\TestCase;

/**
 * Each method under test returns null when the input is acceptable and a
 * MessageBag listing the offending fields when it is not. The assertions
 * check which field failed, not merely that something did.
 *
 * The suite touches the database because the reserveId has to be unique and
 * because availability is a question about the rows already stored.
 */
class BookingValidationsTest extends TestCase
{
    use DatabaseTransactions;

    /** @var BookingValidations */
    private $validations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validations = new BookingValidations();
    }

    /**
     * @return Request
     */
    private function request(array $overrides = [])
    {
        return new Request(array_merge([
            'reserveId' => 'U-BASE',
            'date_beging_reservation' => '30-10-2022 13:49:00',
            'date_ending_reservation' => '30-10-2022 18:00:00',
            'parking_id' => 815,
            'vehicle_registration_number' => '6358YUT',
        ], $overrides));
    }

    public function testValidateCreateAcceptsACompletePayload()
    {
        $this->assertNull($this->validations->validateCreate($this->request()));
    }

    public function testValidateCreateReportsAnEmptyReserveId()
    {
        $errors = $this->validations->validateCreate($this->request(['reserveId' => '']));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('reserveId'));
        $this->assertSame('The number of reserve is required', $errors->first('reserveId'));
    }

    public function testValidateCreateReportsAReserveIdLongerThanTheColumn()
    {
        $errors = $this->validations->validateCreate($this->request([
            'reserveId' => str_repeat('X', 16),
        ]));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('reserveId'));
    }

    /**
     * The format is the one the API documents, d-m-Y, not the database one.
     */
    public function testValidateCreateReportsADateInTheDatabaseFormat()
    {
        $errors = $this->validations->validateCreate($this->request([
            'date_beging_reservation' => '2022-10-30 13:49:00',
        ]));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('date_beging_reservation'));
    }

    public function testValidateCreateReportsAnUnparseableDateInsteadOfThrowing()
    {
        $errors = $this->validations->validateCreate($this->request([
            'date_beging_reservation' => 'not-a-date',
        ]));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('date_beging_reservation'));
    }

    public function testValidateCreateReportsAnEndDateEarlierThanTheBeginDate()
    {
        $errors = $this->validations->validateCreate($this->request([
            'date_beging_reservation' => '30-10-2022 18:00:00',
            'date_ending_reservation' => '30-10-2022 13:00:00',
        ]));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('date_ending_reservation'));
    }

    public function testValidateCreateReportsARangeOfZeroLength()
    {
        $errors = $this->validations->validateCreate($this->request([
            'date_beging_reservation' => '30-10-2022 13:00:00',
            'date_ending_reservation' => '30-10-2022 13:00:00',
        ]));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('date_ending_reservation'));
    }

    public function testValidateCreateReportsANonNumericParkingId()
    {
        $errors = $this->validations->validateCreate($this->request([
            'parking_id' => 'first-floor',
        ]));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('parking_id'));
    }

    public function testValidateReserveIdAcceptsAnIdentifier()
    {
        $this->assertNull($this->validations->validateReserveId('U-123'));
    }

    public function testValidateReserveIdReportsAnEmptyIdentifier()
    {
        $errors = $this->validations->validateReserveId('');

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('reserveId'));
    }

    public function testValidateFiltersAcceptsAnEmptyQueryString()
    {
        $this->assertNull($this->validations->validateFilters(new Request()));
    }

    public function testValidateFiltersReportsAMalformedDate()
    {
        $errors = $this->validations->validateFilters(new Request(['from' => '2022-10-30']));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('from'));
    }

    public function testValidateFiltersReportsAWindowThatEndsBeforeItStarts()
    {
        $errors = $this->validations->validateFilters(new Request([
            'from' => '31-10-2022 10:00:00',
            'to' => '30-10-2022 10:00:00',
        ]));

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('to'));
    }

    public function testValidateAvailabilityAcceptsAFreeParkingSpace()
    {
        $errors = $this->validations->validateAvailability(
            new BookingPer(),
            899,
            '2022-10-30 13:00:00',
            '2022-10-30 14:00:00'
        );

        $this->assertNull($errors);
    }

    public function testValidateAvailabilityReportsAParkingSpaceAlreadyTaken()
    {
        $bookings = new BookingPer();

        $bookings->create($this->request([
            'reserveId' => 'U-AVL',
            'parking_id' => 898,
            'date_beging_reservation' => '30-10-2022 10:00:00',
            'date_ending_reservation' => '30-10-2022 14:00:00',
        ]));

        $errors = $this->validations->validateAvailability(
            $bookings,
            898,
            '2022-10-30 13:00:00',
            '2022-10-30 16:00:00'
        );

        $this->assertNotNull($errors);
        $this->assertTrue($errors->has('parking_id'));
    }
}
