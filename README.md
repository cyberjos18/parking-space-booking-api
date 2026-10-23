# Parking Space Booking API

[![CI](https://github.com/cyberjos18/parking-space-booking-api/actions/workflows/ci.yml/badge.svg)](https://github.com/cyberjos18/parking-space-booking-api/actions/workflows/ci.yml)

A REST API for parking space reservations, built with Laravel 5.8.

It started as a **technical test in October 2022** with two endpoints — create a booking and look one up. In **2026 I came back to it** and added the domain rules the original was missing, completed the booking lifecycle, and rebuilt the test suite so it is actually isolated. The section [*Original test vs. today*](#original-test-vs-today) lists exactly what changed.

> The stack is the one the test specified: Laravel 5.8 on PHP 7.2, with MariaDB. Both the framework and that PHP branch reached end of life after the test was written, and the project is kept on them on purpose, as the snapshot it is. For my current work see [juntos-de-nuevo-family-reunification-platform](https://github.com/cyberjos18/juntos-de-nuevo-family-reunification-platform) (Laravel 13, PHP 8.3, in production).

---

## Architecture

Even for an API this small, the work is split into layers instead of living in the controller:

```
app/
├── Http/Controllers/Bookings/
│   └── BookingsController.php          HTTP only: delegates, then picks a status code
├── Clases/Booking/
│   ├── Validations/
│   │   └── BookingValidations.php      rules and messages, one method per use case
│   └── Persistencia/
│       └── BookingPer.php              every query; returns arrays, never Eloquent models
└── Models/
    └── Booking.php                     schema, the overlap scope, date conversion
```

The rules of the split:

- The **controller** never builds a query and never declares a rule. It asks the validation layer whether the request is acceptable, asks the persistence layer for the data, and translates the outcome into `200`, `404` or `422`. Both collaborators arrive through the constructor, so a test can replace either one.
- The **validation layer** owns every rule, including the domain ones. It answers `null` when the input is acceptable and a `MessageBag` naming the offending fields when it is not, which is what makes the controller a one-line decision.
- The **persistence layer** owns every query and hands back plain arrays already formatted for the API, so the shape of the response never depends on Eloquent.

That same split — a validation class and a repository class per use case — is the one I still use four years later in [juntos-de-nuevo](https://github.com/cyberjos18/juntos-de-nuevo-family-reunification-platform), under `app/Validaciones/` and `app/Persistencia/`.

## Domain rules

Two rules decide whether a booking is accepted, beyond the per-field validation:

| Rule | Why |
|---|---|
| The end of the reservation must be **strictly later** than its beginning | A range of zero or negative length is not a reservation |
| A parking space cannot hold **two overlapping reservations** | A physical space fits one car at a time |

Overlap is `new.begin < existing.end AND new.end > existing.begin`, evaluated per `parking_id`. The comparison is strict, so **back-to-back bookings are allowed**: a reservation may start at the exact minute the previous one ends. Cancelling a booking frees its range immediately.

## API

All dates — in requests and in responses — use **`DD-MM-YYYY HH:MM:SS`**. The database stores `YYYY-MM-DD HH:MM:SS`; the conversion happens inside the model and never leaks out.

### `POST /api/bookings` — create a booking

| Field | Type | Rules |
|---|---|---|
| `reserveId` | string, max 15 | required, unique |
| `date_beging_reservation` | `DD-MM-YYYY HH:MM:SS` | required |
| `date_ending_reservation` | `DD-MM-YYYY HH:MM:SS` | required, later than the beginning |
| `parking_id` | integer | required, must be free for the whole range |
| `vehicle_registration_number` | string, max 15 | required |

```json
{
  "reserveId": "A-1024",
  "date_beging_reservation": "30-10-2022 13:00:00",
  "date_ending_reservation": "30-10-2022 18:00:00",
  "parking_id": 15,
  "vehicle_registration_number": "6358YUT"
}
```

`200` — stored:

```json
{ "status": "ok", "reserveId": "A-1024" }
```

`422` — rejected, with one entry per offending field:

```json
{
  "errors": {
    "parking_id": ["The parking space is already booked for that time range"]
  }
}
```

### `GET /api/bookings/{reserveId}` — one booking

`200`:

```json
{
  "status": "ok",
  "bookingresult": {
    "reserveId": "A-1024",
    "date_beging_reservation": "30-10-2022 13:00:00",
    "date_ending_reservation": "30-10-2022 18:00:00",
    "parking_id": 15,
    "vehicle_registration_number": "6358YUT"
  }
}
```

`404` when no booking carries that identifier.

### `GET /api/bookings` — list bookings

Optional filters, combinable:

| Filter | Meaning |
|---|---|
| `parking_id` | only that parking space |
| `from`, `to` | only bookings **active at some point** inside the window |

```
GET /api/bookings?parking_id=15&from=30-10-2022 00:00:00&to=30-10-2022 23:59:59
```

```json
{
  "status": "ok",
  "count": 1,
  "bookings": [
    {
      "reserveId": "A-1024",
      "date_beging_reservation": "30-10-2022 13:00:00",
      "date_ending_reservation": "30-10-2022 18:00:00",
      "parking_id": 15,
      "vehicle_registration_number": "6358YUT"
    }
  ]
}
```

A booking that merely straddles the window is included: the filter asks *which spaces are busy during this period*, which is the question worth asking of a parking system. `422` when a filter is malformed.

### `DELETE /api/bookings/{reserveId}` — cancel a booking

`200` with the cancelled identifier, `404` when it does not exist.

## Running it locally

Requirements: **PHP 7.2+**, Composer 2, MariaDB 10.3+ (or MySQL 8).

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Point `DB_*` in `.env` at your database, create it, and import the schema. **The `bookings` table is not created by `php artisan migrate`** — the repository's migrations only cover Laravel's own `users` and `password_resets` tables. The schema and its fixtures live in a plain SQL script, which does not create or select a database of its own:

```bash
mysql -u <user> -p -e "CREATE DATABASE <database> DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u <user> -p <database> < database/model/bookings.sql
```

`database/model/` also holds the MySQL Workbench diagram (`bookings.mwb`).

```bash
php artisan serve     # http://127.0.0.1:8000
```

## Tests

45 tests, 108 assertions, in two suites:

```
tests/
├── Feature/   the four endpoints over HTTP: status codes, response bodies, rows written
└── Unit/      validation rules and the model's date conversion, in isolation
```

The suite runs against **its own database**, configured in `phpunit.xml`, so it never writes to the one you develop against. Create it once:

```bash
mysql -u root -P 3307 -e "CREATE DATABASE parking_space_booking_test DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -P 3307 parking_space_booking_test < database/model/bookings.sql
```

```bash
./vendor/bin/phpunit
```

Every test creates the rows it needs and rolls them back through `DatabaseTransactions`, so the suite gives the same result however many times you run it and in whatever order the tests execute.

### What they cover

- **Creation:** the row that lands in the database, the echoed identifier, every missing field reported at once, a `reserveId` longer than its column, a non-numeric `parking_id`
- **Dates:** the API format accepted, the database format rejected, unparseable text rejected — all as `422`, never as an exception
- **Ranges:** end before beginning, and beginning equal to end
- **Overlap:** a booking starting inside another one, a booking swallowing another one whole, the same range on a different space (allowed), and back-to-back on the same space (allowed)
- **Lookup:** the booking returned with the dates in the API format, and `404` when it does not exist
- **Listing:** filtering by space, narrowing to a window, no filters at all, and a malformed filter
- **Cancellation:** the row removed, `404` for an unknown identifier, and the range becoming bookable again afterwards

### Continuous integration

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs the suite on GitHub Actions against a MariaDB 10.11 service container, on the PHP branch the test specified. The service is published on port 3307 with an empty root password, so CI uses the very same connection `phpunit.xml` declares and this README documents — there is no separate CI configuration to keep in sync.

The workflow **runs the suite twice**. The original suite passed once and failed on the second run, because the tests shared rows and never rolled them back; running it twice keeps that regression from coming back unnoticed.

## Original test vs. today

What the October 2022 version did, and what changed in 2026.

**Bugs fixed**

- A malformed date answered **`500`**. `ConvertFormatDateCarbon()` was called while *building* the data to validate, and Carbon throws on anything it cannot parse — including an empty string. The documented `422` for a bad date format was therefore unreachable, and so were its error messages. Validation now runs against the raw input in the format the API documents, and the conversion happens afterwards; `Booking::toDatabaseDate()` returns `null` instead of throwing.
- A lookup for a booking that did not exist answered **`200`** with a message inside the body, which no client could tell apart from a hit. It is now a `404`.
- **The test suite only passed once.** `RefreshDatabase` and `WithFaker` were imported but the traits were never applied, and `phpunit.xml` configured no database, so the tests wrote to the development database. Two tests shared the `reserveId` `125`: the one expecting `422` only passed because the one expecting `200` had inserted that row first. Running the suite a second time failed. Each test now owns its data and rolls it back, against a database of its own.
- One test asserted a `200` from an endpoint that answered `200` whether or not the booking existed, so it could not fail. Six tests carried six assertions, one status code each.
- `Booking::$incrementing` was `false` while the `id` column is `AUTO_INCREMENT`.

**Rules added**

- A parking space can no longer hold two overlapping reservations.
- The end of a reservation must be later than its beginning.
- `reserveId` and `vehicle_registration_number` are now length-checked against their columns, and `parking_id` must be an integer.

**Endpoints added**

- `GET /api/bookings`, with filters by space and by time window.
- `DELETE /api/bookings/{reserveId}`.

**Shape**

- Method names follow PSR-12: `CreateBooking()` and `BookingList()` became `store()`, `show()`, `index()` and `destroy()`.
- `BookingValidations::ValidateBooking($request, "1"|"2")` — one method switching on a magic string — became one method per use case.
- `case '2';` was written with a semicolon instead of a colon.
- The controller built its collaborators with `new` inside every method; they are injected now.
- `.idea/` was tracked in git, and half the commits were `Update workspace.xml`. It is ignored now.

## What I would change today

Things I left as they are, and why they are worth naming:

- **`date_beging_reservation` carries a typo for "beginning"**, and it reached the database column and the public contract. Renaming it now would break every client, which is the lesson: a name costs nothing to get right at the start and cannot be fixed cheaply afterwards.
- **The schema lives in a SQL script, not in migrations.** It cannot be versioned, reviewed per change, or rolled back. Today I would write migrations and keep the script only as a convenience dump.
- **Parking spaces are an integer, not a table.** `parking_id` references nothing, so a booking can point at a space that does not exist. There should be a `parkings` table and a foreign key.
- **Validation lives in classes I call by hand** rather than in Laravel's `FormRequest`, which would move the rules to where the framework expects them and remove the `if ($errors)` from every action.
- **Nothing is authenticated.** The test did not ask for it, but anyone can read, create and now cancel any reservation.
- **Creation answers `200`, not `201`**, and `DELETE` answers `200`, not `204`. Both are kept for compatibility with the contract the original documented.
- **There is no pagination** on the list endpoint, and no upper bound on how long a reservation may last.
- **Laravel's auth scaffolding is still in the tree** (`app/User.php` and five controllers under `Http/Controllers/Auth/`) with no route reaching it. It should have been deleted the day the test was delivered.
