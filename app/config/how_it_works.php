<?php

declare(strict_types=1);

/**
 * Homepage "How It Works" content (spec 11A_spec_how_it_works.md): the five-step customer journey.
 * Static, approved wording kept here (not in the template) so it can be edited in one place; there is no
 * database table for it. The wording describes the real process only:
 *   - the shipping company, not Amponsah Autos, assigns the container and provides its number;
 *   - live shipment status always comes from the official shipping provider's own tracking service;
 *   - the website does not calculate duty and does not claim live tracking of its own.
 */
return [
    'heading' => 'How It Works',
    'intro' => 'From finding your vehicle in Canada to tracking its journey to Ghana, we keep the process clear and straightforward.',
    'steps' => [
        ['title' => 'Browse Vehicles', 'text' => 'Explore available vehicles and find a vehicle that matches your needs.'],
        ['title' => 'Choose Your Vehicle', 'text' => "Review the vehicle's specifications, price, shipping information and other available details."],
        ['title' => 'Contact Amponsah Autos', 'text' => 'Contact the company to discuss and proceed with the selected vehicle.'],
        ['title' => 'Vehicle Ships to Ghana', 'text' => 'After purchase, the company arranges shipment. Once the shipping company assigns a container, the container information becomes available.'],
        ['title' => 'Track Your Vehicle', 'text' => "Once tracking information is available, use the provided container number to access the shipping provider's tracking service."],
    ],
    'cta' => ['label' => 'Browse Vehicles', 'route' => 'vehicles'],
];
