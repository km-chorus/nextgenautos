<?php

declare(strict_types=1);

/**
 * The public website's route table (spec 09_spec_webpage_ui.md).
 *
 * One place that lists every public URL. A route has:
 *   name    short identifier (also used to mark the current item in the navigation)
 *   path    the clean URL, lowercase, ending in a slash
 *   title   the page title
 *   status  'planned'  = the page is built by a later specification: until then the URL shows
 *                         a neutral "not available yet" page (so no link in the header or footer
 *                         is ever broken) and is kept out of search engines (noindex);
 *           'live'     = the route has its own view (the homepage since spec 10; later specifications switch more routes to live).
 *                         A live route may also set 'description', and 'css' / 'js' lists of page-specific files in assets/css and assets/js.
 *
 * Dynamic pages use a pattern with ONE slug segment. The slug is validated by the pattern
 * (lowercase letters, digits, single hyphens, at most `max` characters) before anything else
 * sees it; it is never echoed into a page and never used for a database query here.
 *
 * Development-only routes (component library, error-page preview) exist only when
 * APP_ENV is "development"; in production they are simply not routes (404).
 *
 * This is deliberately a plain array: no routing framework.
 */
return [
    'routes' => [
        ['name' => 'home',         'path' => '/',             'title' => 'Vehicles from Canada and Car Parts in Ghana', 'status' => 'live', 'view' => 'home',
            'description' => 'Quality vehicles sourced from Canada and delivered to Ghana, plus car parts for Toyota, Honda, Hyundai and other popular makes. Browse available vehicles from Mr Amponsah Autos.',
            'css' => ['vehicles.css', 'homepage.css', 'brand-strip.css', 'parts.css', 'how-it-works.css', 'utilities.css', 'about.css'], 'js' => ['vehicle-search.js', 'homepage.js', 'brand-strip.js', 'parts.js']],
        ['name' => 'vehicles',     'path' => '/vehicles/',     'title' => 'Vehicles',          'status' => 'live', 'view' => 'vehicles',
            'description' => 'Browse vehicles sourced from Canada and delivered to Ghana. Search by make, model, year and price.',
            'css' => ['vehicles.css'], 'js' => ['vehicle-search.js', 'inventory.js', 'vehicle-gallery.js']],
        ['name' => 'brands',       'path' => '/brands/',       'title' => 'Brands',            'status' => 'planned'],
        // Spec 16P (car parts catalogue) builds this page; until then the "Parts and Accessories" button shows the neutral "coming soon" page.
        ['name' => 'parts',        'path' => '/parts/',        'title' => 'Parts and Accessories', 'status' => 'planned'],
        ['name' => 'how-it-works', 'path' => '/how-it-works/', 'title' => 'How It Works',      'status' => 'planned'],
        // Spec 13: signed-in customers only (PublicController checks the session before anything is shown).
        ['name' => 'track',        'path' => '/track/',        'title' => 'Track Vehicle',     'status' => 'live', 'view' => 'track', 'private' => true,
            'css' => ['how-it-works.css', 'track.css']],
        ['name' => 'about',        'path' => '/about/',        'title' => 'About Us',          'status' => 'planned'],
        ['name' => 'team',         'path' => '/team/',         'title' => 'Our Team',          'status' => 'planned'],
        // Spec 17: contact details from the admin Settings, WhatsApp chat and the contact form (POST).
        ['name' => 'contact',      'path' => '/contact/',      'title' => 'Contact Us',        'status' => 'live', 'view' => 'contact', 'methods' => ['POST'],
            'css' => ['contact.css'], 'js' => ['form-guard.js']],
        ['name' => 'faq',          'path' => '/faq/',          'title' => 'FAQ',               'status' => 'planned'],
        // Spec 19: the site assistant's answers (JSON for assets/js/assistant.js; GET only, never indexed, never cached).
        ['name' => 'assistant',    'path' => '/assistant/',    'title' => 'Assistant',         'status' => 'live', 'view' => 'assistant', 'private' => true],
        // Spec 18: cookie policy and settings; POST saves the visitor's choice (also the target of the cookie banner).
        ['name' => 'cookies',      'path' => '/cookies/',      'title' => 'Cookie Settings',   'status' => 'live', 'view' => 'cookies', 'methods' => ['POST'],
            'description' => 'The cookies this website uses and how to choose whether your browser keeps copies of the pages you visit.'],
        // Customer accounts (spec 13). 'private' = never indexed and never cached; 'methods' lists the non-GET methods accepted.
        ['name' => 'login',        'path' => '/login/',        'title' => 'Sign In',           'status' => 'live', 'view' => 'login',    'private' => true, 'methods' => ['POST'], 'css' => ['account.css'], 'js' => ['account.js']],
        ['name' => 'register',     'path' => '/register/',     'title' => 'Create Account',    'status' => 'live', 'view' => 'register', 'private' => true, 'methods' => ['POST'], 'css' => ['account.css'], 'js' => ['account.js'], 'parent' => 'login'],
        ['name' => 'google-start',    'path' => '/auth/google/',          'title' => 'Continue with Google', 'status' => 'live', 'view' => 'google-start',    'private' => true],
        ['name' => 'google-callback', 'path' => '/auth/google/callback/', 'title' => 'Continue with Google', 'status' => 'live', 'view' => 'google-callback', 'private' => true],
        ['name' => 'account',      'path' => '/account/',      'title' => 'My Account',        'status' => 'live', 'view' => 'account',  'private' => true, 'methods' => ['POST'], 'css' => ['account.css'], 'js' => ['account.js']],
    ],

    'patterns' => [
        ['name' => 'brand',   'regex' => '#^/brands/([a-z0-9]+(?:-[a-z0-9]+)*)/$#', 'max' => 100, 'title' => 'Brand',   'status' => 'planned', 'parent' => 'brands'],
        // Spec 14: the vehicle detail page; POST = its Contact and Reserve forms.
        ['name' => 'vehicle', 'regex' => '#^/vehicle/([a-z0-9]+(?:-[a-z0-9]+)*)/$#', 'max' => 160, 'title' => 'Vehicle', 'status' => 'live', 'view' => 'vehicle', 'parent' => 'vehicles',
            'methods' => ['POST'], 'css' => ['vehicles.css', 'vehicle-detail.css'], 'js' => ['vehicle-detail.js', 'form-guard.js']],
    ],

    'development' => [
        ['name' => 'styleguide',       'path' => '/styleguide/',       'title' => 'Component library', 'status' => 'live', 'view' => 'styleguide'],
        ['name' => 'styleguide-error', 'path' => '/styleguide/error/', 'title' => 'Error page preview', 'status' => 'live', 'view' => 'error-preview'],
    ],
];
