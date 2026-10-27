<?php

declare(strict_types=1);

/**
 * The 50 main part types from the client's picture "Complete car parts list with pictures" (spec 26), for the one-off
 * import into Admin > Parts (spec 27): php database/tools/import_parts_catalogue.php
 *
 * slug = the part's web address; category = an existing part category slug; image = a file in this folder, or null
 * (steering wheel, trunk lid and airbag: the pictures show a large Toyota badge). No price, condition, stock, part
 * number or compatibility: the client has not given them; the team adds them in the admin.
 */
return [
    ['engine', 'Engine', 'engine', 'engine.webp'],
    ['fuel-tank', 'Fuel Tank', 'engine', 'fuel-tank.webp'],
    ['fuel-pump', 'Fuel Pump (in tank)', 'engine', 'fuel-pump.webp'],
    ['catalytic-converter', 'Catalytic Converter', 'engine', 'catalytic-converter.webp'],
    ['exhaust-assembly', 'Exhaust Assembly', 'engine', 'exhaust-assembly.webp'],
    ['gearbox', 'Transmission / Gearbox', 'transmission', 'gearbox.webp'],
    ['cv-axle', 'Front Axle / CV Axle', 'transmission', 'cv-axle.webp'],
    ['alternator', 'Alternator', 'electrical', 'alternator.webp'],
    ['starter-motor', 'Starter Motor', 'electrical', 'starter-motor.webp'],
    ['ecu', 'ECU / Engine Computer', 'electrical', 'ecu.webp'],
    ['abs-module', 'ABS Module', 'electrical', 'abs-module.webp'],
    ['wiring-harness', 'Wiring Harness', 'electrical', 'wiring-harness.webp'],
    ['instrument-cluster', 'Instrument Cluster', 'electrical', 'instrument-cluster.webp'],
    ['infotainment', 'Infotainment Screen / Radio', 'electrical', 'infotainment.webp'],
    ['radiator', 'Radiator', 'cooling', 'radiator.webp'],
    ['cooling-fan', 'Cooling Fan', 'cooling', 'cooling-fan.webp'],
    ['ac-compressor', 'AC Compressor', 'cooling', 'ac-compressor.webp'],
    ['ac-condenser', 'AC Condenser', 'cooling', 'ac-condenser.webp'],
    ['heater-core', 'Heater Core', 'cooling', 'heater-core.webp'],
    ['front-suspension-left', 'Front Suspension (Left)', 'suspension-steering', 'front-suspension-left.webp'],
    ['front-suspension-right', 'Front Suspension (Right)', 'suspension-steering', 'front-suspension-right.webp'],
    ['rear-suspension', 'Rear Suspension', 'suspension-steering', 'rear-suspension.webp'],
    ['steering-rack', 'Steering Rack', 'suspension-steering', 'steering-rack.webp'],
    ['steering-wheel', 'Steering Wheel', 'suspension-steering', null],
    ['wheel-hub', 'Wheel Hub / Knuckle', 'suspension-steering', 'wheel-hub.webp'],
    ['subframe', 'Subframe', 'suspension-steering', 'subframe.webp'],
    ['brake-caliper', 'Brake Caliper', 'brakes', 'brake-caliper.webp'],
    ['brake-disc', 'Brake Disc (Rotor)', 'brakes', 'brake-disc.webp'],
    ['front-bumper', 'Front Bumper', 'body-lights', 'front-bumper.webp'],
    ['rear-bumper', 'Rear Bumper', 'body-lights', 'rear-bumper.webp'],
    ['headlight', 'Headlight', 'body-lights', 'headlight.webp'],
    ['tail-light', 'Tail Light', 'body-lights', 'tail-light.webp'],
    ['front-door', 'Front Door', 'body-lights', 'front-door.webp'],
    ['rear-door', 'Rear Door', 'body-lights', 'rear-door.webp'],
    ['bonnet', 'Bonnet / Hood', 'body-lights', 'bonnet.webp'],
    ['trunk-lid', 'Trunk Lid / Tailgate', 'body-lights', null],
    ['fender', 'Fender', 'body-lights', 'fender.webp'],
    ['side-mirror', 'Side Mirror', 'body-lights', 'side-mirror.webp'],
    ['window-glass', 'Windows (Glass)', 'body-lights', 'window-glass.webp'],
    ['window-regulator', 'Window Regulator', 'body-lights', 'window-regulator.webp'],
    ['front-seat-driver', 'Front Seat (Driver)', 'interior', 'front-seat-driver.webp'],
    ['front-seat-passenger', 'Front Seat (Passenger)', 'interior', 'front-seat-passenger.webp'],
    ['rear-seats', 'Rear Seats', 'interior', 'rear-seats.webp'],
    ['dashboard', 'Dashboard', 'interior', 'dashboard.webp'],
    ['airbag', 'Airbag', 'interior', null],
    ['seat-belt', 'Seat Belt', 'interior', 'seat-belt.webp'],
    ['rear-view-mirror', 'Rear View Mirror', 'interior', 'rear-view-mirror.webp'],
    ['interior-trim', 'Interior Trim (Panels)', 'interior', 'interior-trim.webp'],
    ['wheels-rims', 'Wheels / Rims', 'wheels-tyres', 'wheels-rims.webp'],
    ['tyre', 'Tyre', 'wheels-tyres', 'tyre.webp'],
];
