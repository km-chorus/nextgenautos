<?php

declare(strict_types=1);

/**
 * Homepage hero slides (spec 10_spec_homepage_hero.md).
 *
 * The hero shows real photographs only: nothing is generated or invented. The four photographs now installed are
 * licensed stock photographs (Unsplash License, free for commercial use); their sources are recorded in
 * Context/08_hero_image_sources.md. They are placeholders for the company's own photography: replace the files in
 * assets/images/hero/ with approved photographs of the same names whenever they exist. With no usable image the hero
 * shows the plain dark panel with the headline and the search.
 *
 * To add an approved photograph, save optimized files (WebP preferred; JPEG or PNG also work) in
 * assets/images/hero/ using these names (n = 1 to 4; the extension may be .webp, .jpg or .png) and nothing else
 * needs to change:
 *
 *   hero-n-desktop        REQUIRED  wide crop, about 1600 x 900 (kept to roughly 150 KB or less)
 *   hero-n-mobile         optional  portrait/square crop, about 800 x 1000 (used up to 767 px wide;
 *                                   without it the desktop file is used)
 *   hero-n-wide           optional  larger version for big screens, about 2200 x 1000
 *
 * A slide whose desktop file is missing, or is not really the image type its extension says, is skipped. Slide 1 (put the strongest
 * image there) always opens; the rotation order is fixed.
 *
 *   id     slide number n (1 to 4; at most 4 slides are used)
 *   alt    description of the photograph for screen readers; leave '' only while the image is purely
 *          decorative. Describe what the real photograph shows when it is added.
 *   focus  where the subject sits, so a crop keeps it visible: center | left | right | top | bottom
 */
return [
    'directory' => 'assets/images/hero',
    'slides' => [
        ['id' => 1, 'alt' => 'Aerial view of a large lot of parked vehicles', 'focus' => 'center'],
        ['id' => 2, 'alt' => 'Two vehicles loaded on a car carrier truck', 'focus' => 'center'],
        ['id' => 3, 'alt' => 'Aerial view of a container port terminal with stacked shipping containers and cranes', 'focus' => 'center'],
        ['id' => 4, 'alt' => 'Cars and vans parked on the vehicle deck of a ship', 'focus' => 'center'],
    ],
];
