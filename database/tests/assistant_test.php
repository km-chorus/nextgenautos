<?php

declare(strict_types=1);

/**
 * Tests for the site assistant (spec 19): question matching, answers built only from the given data, honest answers
 * when data is missing, safe links, input cleaning, and the /assistant/ route. Uses fixed test data (no database).
 * Run: php database/tests/assistant_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/AssistantService.php';
require_once dirname(__DIR__, 2) . '/app/services/PublicRouter.php';
require_once dirname(__DIR__, 2) . '/app/helpers/public_ui.php';

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
    if ($condition) { $pass++; echo "  PASS  $name\n"; }
    else { $fail++; echo "  FAIL  $name" . ($detail !== '' ? "  -> $detail" : '') . "\n"; }
};

/** Fixed data in place of the database. */
final class FakeData extends AssistantData
{
    public function __construct(public ?array $v, public ?array $p, public ?array $c, public ?array $b = ['Toyota', 'Honda', 'Hyundai']) {}
    public function vehicles(): ?array { return $this->v; }
    public function parts(): ?array { return $this->p; }
    public function brands(): ?array { return $this->b; }
    public function contact(): ?array { return $this->c; }
    public function dutyUrl(): string { return 'https://duty.example/calc'; }
    public function trackingUrl(): string { return 'https://track.example/'; }
}
$vehicles = [
    ['title' => '2023 Honda Accord EX', 'brand' => 'Honda', 'name' => 'Accord EX', 'year' => '2023', 'price' => 'GHS 12,388', 'status' => 'Available', 'duty' => false, 'url' => '/vehicle/2023-honda-accord/'],
    ['title' => '2019 Honda Civic', 'brand' => 'Honda', 'name' => 'Civic', 'year' => '2019', 'price' => 'GHS 9,000', 'status' => 'Reserved', 'duty' => true, 'url' => '/vehicle/2019-honda-civic/'],
    ['title' => '2011 Mitsubishi Zonda', 'brand' => 'Mitsubishi', 'name' => 'Zonda', 'year' => '2011', 'price' => 'GHS 6,545', 'status' => 'Available', 'duty' => false, 'url' => '/vehicle/2011-mitsubishi-zonda/'],
];
$parts = [
    ['name' => 'Front brake pads', 'slug' => 'front-brake-pads', 'part_number' => '04465-02220', 'type' => 'Brakes', 'condition' => 'New', 'price' => 'GHS 450', 'in_stock' => true,
        'fits' => ['Toyota Corolla 2014–2018'], 'fits_more' => 0, 'makes' => ['toyota']],
    ['name' => 'Phone holder', 'slug' => 'phone-holder', 'part_number' => null, 'type' => 'Accessories', 'condition' => 'New', 'price' => 'GHS 60', 'in_stock' => false, 'fits' => [], 'fits_more' => 0, 'makes' => []],
];
$contact = ['phones' => [['display' => '+1 416-577-3963', 'tel' => '+14165773963'], ['display' => '055 660 5451', 'tel' => '+233556605451']],
    'whatsapp' => 'https://wa.me/233556605451?text=Hello', 'email' => null, 'hours' => "Mon - Fri: 8:00 - 17:00\nSat: 9:00 - 14:00"];
$bot = new AssistantService(new FakeData($vehicles, $parts, $contact));
$text = static fn (array $r): string => implode(' ', array_column($r['messages'], 'text'));
$links = static fn (array $r): array => array_merge(...array_map(static fn (array $m): array => array_column($m['links'], 'href'), $r['messages']));
$items = static fn (array $r): array => array_merge(...array_map(static fn (array $m): array => array_column($m['items'], 'title'), $r['messages']));
$ask = static fn (string $q): array => $bot->reply(null, $q);

echo "Question matching\n";
foreach ([
    'hi there' => 'start', 'How much is it?' => 'prices', 'how do I track my car' => 'tracking', 'where is my container' => 'tracking',
    'is import duty included' => 'duty', 'do you sell brake pads' => 'parts', 'order brake pads' => 'parts', 'what time do you open' => 'hours',
    'where is your showroom' => 'location', 'can I pay with mobile money' => 'payment', 'how long does shipping take' => 'shipping',
    'how do I reserve a car' => 'reserve', 'what is your phone number' => 'contact', 'how does it work' => 'process', 'can I book a test drive' => 'test_drive',
    'I want to speak to a human' => 'human', 'thanks!' => 'thanks', 'how do I create an account' => 'account', 'which brands do you have' => 'brands',
    'qwerty asdf' => null,
] as $q => $want) {
    $check('"' . $q . '" -> ' . json_encode($want), AssistantService::detect($q) === $want, json_encode(AssistantService::detect($q)));
}

echo "\nAnswers from real data only\n";
$r = $bot->reply('vehicles', null);
$check('vehicles: every listed vehicle with its link, and a link to all', $items($r) === ['2023 Honda Accord EX', '2019 Honda Civic', '2011 Mitsubishi Zonda'] && in_array('/vehicles/', $links($r), true));
$r = $bot->reply('prices', null);
$check('prices: real prices, and the duty note', str_contains(json_encode($r), 'GHS 12,388') && str_contains(json_encode($r), 'Duty included') && str_contains(json_encode($r), 'do not include Ghana import duty'));
$r = $ask('how much is the accord?');
$check('a named model answers with that vehicle and its price', $items($r) === ['2023 Honda Accord EX'] && $r['messages'][0]['items'][0]['price'] === 'GHS 12,388');
$r = $ask('do you have honda');
$check('a make lists all its vehicles', $items($r) === ['2023 Honda Accord EX', '2019 Honda Civic']);
$r = $ask('2011 cars');
$check('a year matches', $items($r) === ['2011 Mitsubishi Zonda']);
$r = $ask('do you have a toyota?');
$check('a known make with nothing listed: says so, invents nothing, offers contact', str_contains($text($r), 'no Toyota listed') && $items($r) === [] && in_array('tel:+14165773963', $links($r), true));
$r = $ask('brake pads for corolla');
$check('parts: matched by name and compatible vehicle, link asks about that part', $items($r) === ['Front brake pads'] && str_contains(json_encode($r), 'Fits: Toyota Corolla') && $r['messages'][0]['items'][0]['href'] === '/contact/?part=front-brake-pads#message');
$r = $ask('do you sell parts');
$check('parts: general question lists the latest parts (universal accessory says "ask us")', $items($r) === ['Front brake pads', 'Phone holder'] && str_contains(json_encode($r), 'Fits: ask us') && str_contains(json_encode($r), 'Out of stock'));
$r = $ask('do you have a gearbox part');
$check('parts: an unlisted part is not invented', str_contains($text($r), "couldn't find that part") && $items($r) === []);
$r = $bot->reply('hours', null);
$check('hours: the stored hours, line by line', $r['messages'][0]['lines'] === ['Mon - Fri: 8:00 - 17:00', 'Sat: 9:00 - 14:00']);
$r = $bot->reply('contact', null);
$check('contact: WhatsApp, both phones, contact page (no email when none is stored)', $links($r) === ['https://wa.me/233556605451?text=Hello', 'tel:+14165773963', 'tel:+233556605451', '/contact/']);
$r = $bot->reply('duty', null);
$check('duty: never calculates, links the approved calculator', str_contains($text($r), 'does not calculate') && in_array('https://duty.example/calc', $links($r), true) && preg_match('/GHS\s?\d/', $text($r)) !== 1);
$r = $bot->reply('tracking', null);
$check('tracking: container number from the shipping line, no invented status', str_contains($text($r), 'container number') && in_array('https://track.example/', $links($r), true) && in_array('/track/', $links($r), true));
$r = $bot->reply('shipping', null);
$check('shipping: no invented delivery time', str_contains($text($r), 'not listed on the website') && preg_match('/\d+\s*(day|week|month)/i', $text($r)) !== 1);
$r = $bot->reply('location', null);
$check('location: no address is invented', str_contains($text($r), 'address is not listed'));
$r = $bot->reply('payment', null);
$check('payment: no online payment', str_contains($text($r), 'No payment is taken on this website'));
$r = $bot->reply('process', null);
$check('process: the five How It Works steps, numbered', count($r['messages'][0]['lines']) === 5 && $r['messages'][0]['numbered'] === true);
$r = $ask('blah blah nonsense');
$check('unknown question: honest fallback with contact options and menu chips', str_contains($text($r), "couldn't find an answer") && count($r['chips']) === 4 && in_array('/contact/', $links($r), true));
$r = $bot->reply('start', null);
$check('start: says it is automated, eight quick replies', str_contains($text($r), 'automated') && count($r['chips']) === 8 && $r['chips'][0] === ['id' => 'vehicles', 'label' => 'Vehicles']);
$check('the greeting uses the same site name as the header', AssistantService::SITE_NAME === PUBLIC_SITE_NAME && str_contains($text($r), PUBLIC_SITE_NAME));
$check('no answer contains an emoji', preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', json_encode(array_map(static fn (string $i): array => $bot->reply($i, null), AssistantService::INTENTS), JSON_UNESCAPED_UNICODE)) !== 1);

echo "\nMissing data\n";
$empty = new AssistantService(new FakeData([], [], ['phones' => [], 'whatsapp' => null, 'email' => null, 'hours' => null]));
$check('no vehicles listed: says so', str_contains($text($empty->reply('vehicles', null)), 'No vehicles are listed'));
$check('no parts yet: "coming soon"', str_contains($text($empty->reply('parts', null)), 'coming soon'));
$check('no hours stored: says so', str_contains($text($empty->reply('hours', null)), 'not listed on the website yet'));
$check('no contact details at all: still offers the contact page', $links($empty->reply('contact', null)) === ['/contact/']);
$down = new AssistantService(new FakeData(null, null, null, null));
$check('database down: "could not be loaded", never an error', str_contains($text($down->reply('vehicles', null)), 'could not be loaded') && str_contains($text($down->reply('parts', null)), 'could not be loaded'));

echo "\nInput and safety\n";
$check('intent from a fixed list only (anything else = treated as text)', $bot->reply('<script>', null)['chips'] !== [] && $bot->reply(['vehicles'], null) === $bot->reply('start', null));
$check('empty, array or non-UTF-8 question = greeting', $bot->reply(null, '') === $bot->reply('start', null) && $bot->reply(null, ['a']) === $bot->reply('start', null) && $bot->reply(null, "\xC3\x28") === $bot->reply('start', null));
$check('question cleaned: control characters removed, capped at 200', AssistantService::cleanQuestion("a\x00b\u{202E}c") === 'a b c' && mb_strlen((string) AssistantService::cleanQuestion(str_repeat('x', 900))) === 200);
$check('a hostile question is never echoed back', !str_contains(json_encode($ask('<img src=x onerror=alert(1)> price')), 'onerror'));
$allLinks = [];
foreach (AssistantService::INTENTS as $i) { $allLinks = array_merge($allLinks, $links($bot->reply($i, null))); }
$check('every link is a site path, tel:, mailto: or https:', $allLinks !== [] && array_filter($allLinks, static fn (string $h): bool => preg_match('#^(/(?!/)|tel:|mailto:|https://)#', $h) !== 1) === []);

echo "\nRoute\n";
$config = require dirname(__DIR__, 2) . '/app/config/public_routes.php';
$router = new PublicRouter($config, false);
$res = $router->resolve('GET', '/assistant/?q=hello');
$check('/assistant/ is a private live route', $res['type'] === 'route' && $res['route']['view'] === 'assistant' && !empty($res['route']['private']));
$check('/assistant/ refuses POST', $router->resolve('POST', '/assistant/')['type'] === 'method_not_allowed');

echo "\nResult: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
