<?php

declare(strict_types=1);

/**
 * Homepage wording outside its own sections: the hero heading and line, and the "why choose us" trust cards.
 * Static, approved wording kept here (not in the template) so it can be edited in one place; there is no database table for it.
 *   - Business lines (owner, 2026-10-14): vehicle imports from Canada to Ghana, and car parts for popular makes.
 *     The site has no parts catalogue, so the parts wording names makes as examples only and promises no stock or prices.
 *   - Trust cards state only what the business and this website really do: no statistics, ratings, testimonials or
 *     badges (spec 10A sections 25 and 26).
 */
return [
    'title' => 'Find Your Next Vehicle.',
    'text' => 'Quality vehicles sourced from Canada and delivered to Ghana, plus car parts for Toyota, Honda, Hyundai and other popular makes.',
    'trust' => [
        ['icon' => 'car',        'title' => 'Sourced from Canada',    'text' => 'Vehicles selected in Canada and shipped to Ghana.'],
        ['icon' => 'engine',     'title' => 'Car parts too',          'text' => 'Parts for Toyota, Honda, Hyundai and other popular makes.'],
        ['icon' => 'calculator', 'title' => 'Import duty estimate',   'text' => 'Estimate Ghana import duty with the external duty calculator.'],
        ['icon' => 'route',      'title' => 'Real shipment tracking', 'text' => 'Follow your vehicle with the container number from the shipping line.'],
    ],
];
