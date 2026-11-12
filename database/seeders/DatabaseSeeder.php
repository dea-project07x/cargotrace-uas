<?php

namespace Database\Seeders;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\User;
use App\Services\ExceptionService;
use App\Services\ShipmentService;
use App\Services\TrackingService;
use Illuminate\Database\Seeder;

/**
 * Demo data (synthetic but consistent). Everything goes through the real services,
 * so statuses, ETA/delay results, notifications and audit logs are produced by
 * the same business rules the API uses.
 *
 * Demo accounts (password: "password"):
 *   admin@cargotrace.test, operator@cargotrace.test,
 *   customer1@cargotrace.test, customer2@cargotrace.test
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = fn (string $name, string $email, UserRole $role) => User::factory()->create([
            'name' => $name, 'email' => $email, 'role' => $role,
        ]);

        $user('Admin CargoTrace', 'admin@cargotrace.test', UserRole::Admin);
        $operator = $user('Operator Logistik', 'operator@cargotrace.test', UserRole::Operator);
        $c1 = $user('PT Samudra Retail', 'customer1@cargotrace.test', UserRole::Customer);
        $c2 = $user('Harbor Foods Ltd', 'customer2@cargotrace.test', UserRole::Customer);

        $carrier = [
            'pbl' => Carrier::create(['name' => 'Pacific Blue Lines', 'code' => 'PBL', 'contact_email' => 'ops@pbl.example']),
            'nsc' => Carrier::create(['name' => 'Nusantara Cargo', 'code' => 'NSC', 'contact_email' => 'ops@nsc.example']),
            'eaf' => Carrier::create(['name' => 'EuroAsia Freight', 'code' => 'EAF', 'contact_email' => 'ops@eaf.example']),
        ];

        $loc = fn (string $name, string $code, string $city, string $cc, float $lat, float $lng) => Location::create([
            'name' => $name, 'code' => $code, 'city' => $city, 'country_code' => $cc, 'latitude' => $lat, 'longitude' => $lng,
        ]);

        $jkt = $loc('Tanjung Priok Port', 'IDJKT', 'Jakarta', 'ID', -6.1067, 106.8800);
        $sub = $loc('Tanjung Perak Port', 'IDSUB', 'Surabaya', 'ID', -7.2000, 112.7300);
        $sin = $loc('Port of Singapore', 'SGSIN', 'Singapore', 'SG', 1.2644, 103.8200);
        $pkg = $loc('Port Klang', 'MYPKG', 'Klang', 'MY', 3.0000, 101.3900);
        $rtm = $loc('Port of Rotterdam', 'NLRTM', 'Rotterdam', 'NL', 51.9500, 4.1400);
        $syd = $loc('Port Botany', 'AUSYD', 'Sydney', 'AU', -33.9667, 151.2167);

        $shipments = app(ShipmentService::class);
        $tracking = app(TrackingService::class);
        $exceptions = app(ExceptionService::class);

        /**
         * Hours are "hours ago" (negative = in the future). Each step:
         * [hoursAgo, status|null, Location|null, description, etaShiftHoursVsExpected|null]
         */
        $spawn = function ($customer, $carrierModel, $origin, $dest, string $cargo, float $kg,
            int $createdH, int $depH, int $arrH, array $steps) use ($shipments, $tracking, $operator) {
            $s = $shipments->create([
                'customer_id' => $customer->id,
                'carrier_id' => $carrierModel->id,
                'origin_id' => $origin->id,
                'destination_id' => $dest->id,
                'cargo_type' => $cargo,
                'weight_kg' => $kg,
                'volume_cbm' => round($kg / 90, 2),
                'expected_departure_at' => now()->subHours($depH),
                'expected_arrival_at' => now()->subHours($arrH),
                'items' => [['description' => $cargo, 'quantity' => 10, 'unit_weight_kg' => round($kg / 10, 2)]],
            ], $operator, now()->subHours($createdH));

            foreach ($steps as [$hAgo, $status, $location, $desc, $etaShift]) {
                $tracking->addEvent($s, [
                    'status' => $status,
                    'event_type' => 'UPDATE',
                    'occurred_at' => now()->subHours($hAgo)->toIso8601String(),
                    'location_id' => $location?->id,
                    'description' => $desc,
                    'estimated_arrival_at' => $etaShift !== null
                        ? $s->expected_arrival_at->copy()->addHours($etaShift)->toIso8601String()
                        : null,
                ], $operator);
            }

            return $s->refresh();
        };

        $B = ShipmentStatus::Booked->value;
        $P = ShipmentStatus::PickedUp->value;
        $T = ShipmentStatus::InTransit->value;
        $D = ShipmentStatus::Delivered->value;

        // 1. Delivered on time
        $spawn($c1, $carrier['pbl'], $jkt, $rtm, 'Electronics', 12000, 480, 456, 48, [
            [470, $B, $jkt, 'Booking confirmed', null],
            [456, $P, $jkt, 'Cargo picked up at Tanjung Priok', null],
            [450, $T, $sin, 'Departed Singapore transshipment', null],
            [200, null, null, 'Vessel in open sea, no position report', null],
            [60, $D, $rtm, 'Delivered to consignee', null],
        ]);

        // 2. Delivered late (~3h => LOW)
        $spawn($c2, $carrier['nsc'], $sub, $syd, 'Frozen food', 8000, 300, 280, 40, [
            [290, $B, $sub, 'Booking confirmed', null],
            [280, $P, $sub, 'Picked up', null],
            [270, $T, $sin, 'Departed Singapore', null],
            [37, $D, $syd, 'Delivered to cold storage', null],
        ]);

        // 3. In transit, on time
        $spawn($c1, $carrier['nsc'], $jkt, $syd, 'Textiles', 5000, 100, 90, -120, [
            [95, $B, $jkt, 'Booking confirmed', null],
            [90, $P, $jkt, 'Picked up', null],
            [80, $T, $sin, 'Departed Singapore', 0],
        ]);

        // 4. In transit, delayed +12h => MEDIUM
        $spawn($c2, $carrier['pbl'], $jkt, $rtm, 'Machinery parts', 15000, 400, 380, -48, [
            [395, $B, $jkt, 'Booking confirmed', null],
            [380, $P, $jkt, 'Picked up', null],
            [370, $T, $pkg, 'Departed Port Klang', null],
            [30, null, null, 'Port congestion reported ahead, ETA revised', 12],
        ]);

        // 5. In transit, delayed +30h => HIGH, with an open customs-hold exception
        $s5 = $spawn($c1, $carrier['eaf'], $sub, $rtm, 'Furniture', 9000, 500, 480, -10, [
            [495, $B, $sub, 'Booking confirmed', null],
            [480, $P, $sub, 'Picked up', null],
            [470, $T, $sin, 'Departed Singapore', null],
            [12, null, $rtm, 'Arrived at destination port area', 30],
        ]);
        $exceptions->open($s5, [
            'type' => 'CUSTOMS_HOLD',
            'severity' => 'HIGH',
            'description' => 'Documents under inspection by customs.',
            'location_id' => $rtm->id,
        ], $operator);

        // 6. Created only
        $spawn($c2, $carrier['nsc'], $jkt, $sin, 'Spare parts', 1200, 5, -48, -500, []);

        // 7. Booked
        $spawn($c1, $carrier['pbl'], $sub, $syd, 'Packaged goods', 2500, 30, -20, -400, [
            [25, $B, $sub, 'Booking confirmed', null],
        ]);
    }
}
