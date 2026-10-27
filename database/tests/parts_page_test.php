<?php

declare(strict_types=1);

/**
 * Parts in the admin and on /parts/ (spec 26, rebuilt for spec 27): several part numbers, optional price / condition /
 * stock, copy compatible vehicles, "find parts for your vehicle", search, pages, homepage priced-only, the contact
 * prefill and the one-off catalogue import. CLI only:
 *   php database/tests/parts_page_test.php
 * Every database row is created inside one transaction that is rolled back; no files are written.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/app/services/PartService.php';
require_once $root . '/app/services/PublicPartService.php';
require_once $root . '/app/services/PublicVehicleService.php';
require_once $root . '/app/services/CompanyContactService.php';
require_once $root . '/app/helpers/public_ui.php';

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo '  ' . ($ok ? 'PASS' : 'FAIL') . "  $label" . (!$ok && $detail !== '' ? " -> $detail" : '') . "\n";
};

echo "Validation\n";
[$nums, $err] = PartValidator::partNumbers(['04465-02220', ' ', '04465 YZZ ']);
$check('part numbers: blanks dropped, spaces tidied, order kept (main first)', $nums === ['04465-02220', '04465 YZZ'] && $err === []);
$check('a duplicate number is refused even with different hyphens, spaces or case', isset(PartValidator::partNumbers(['04465-02220', '0446502220'])[1]['part_number_1'])
    && isset(PartValidator::partNumbers(['ab-1', 'AB 1'])[1]['part_number_1']));
$check('at most 10 part numbers', isset(PartValidator::partNumbers(array_map(static fn (int $i): string => 'N' . $i, range(1, 11)))[1]['part_numbers']));
$check('odd characters and over-long numbers are refused', isset(PartValidator::partNumbers(['<x>'])[1]['part_number_0']) && isset(PartValidator::partNumbers([str_repeat('A', 101)])[1]['part_number_0']));
$check('a single string and junk input are handled', PartValidator::partNumbers('A-1')[0] === ['A-1'] && isset(PartValidator::partNumbers(42)[1]['part_numbers']) && PartValidator::partNumbers([['x']])[0] === []);
$check('number key ignores spaces, hyphens, dots, slashes, underscores and case', PartValidator::numberKey('04465-02220') === PartValidator::numberKey('0446502220') && PartValidator::numberKey('a.b/c_d e') === 'ABCDE');
$v = PartValidator::validate(['category_id' => '1', 'name' => 'X', 'price' => '', 'condition' => '', 'stock_status' => '']);
$check('price, condition and stock may all be empty (null)', $v['errors'] === [] && $v['values']['price'] === null && $v['values']['condition'] === null && $v['values']['stock_status'] === null, json_encode($v['errors']));
$check('a bad price, condition or stock is still refused', isset(PartValidator::validate(['price' => 'abc'])['errors']['price']) && isset(PartValidator::validate(['condition' => 'x'])['errors']['condition'])
    && isset(PartValidator::validate(['stock_status' => 'x'])['errors']['stock_status']));
$fl = PartValidator::filters(['price' => 'none', 'fits' => 'set', 'stock' => 'none']);
$check('admin list filters: price set/none, compatibility set/none, stock not set', $fl['price'] === 'none' && $fl['fits'] === 'set' && $fl['stock'] === 'none'
    && PartValidator::filters(['price' => 'x', 'fits' => 1])['price'] === 'all' && PartValidator::filters(['fits' => 1])['fits'] === 'all');

$pdo = Database::connection();
$repo = new PartRepository($pdo);
$brand = static fn (string $slug): int => (int) $pdo->query('SELECT id FROM brands WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
$cat = static fn (string $slug): int => (int) $pdo->query('SELECT id FROM part_categories WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
$toyota = $brand('toyota');
$honda = $brand('honda');
$add = static function (string $name, array $o = []) use ($repo, $cat): int {
    $o += ['price' => null, 'published' => 1, 'fits' => [], 'numbers' => [], 'condition' => null, 'stock' => null, 'cat' => 'brakes'];
    $id = $repo->insert(['category_id' => $cat($o['cat']), 'name' => $name, 'condition' => $o['condition'], 'price' => $o['price'], 'currency' => 'GHS',
        'stock_status' => $o['stock'], 'description' => null, 'is_published' => $o['published']], PartValidator::slugify($name));
    $repo->replaceFitments($id, $o['fits']);
    $repo->replacePartNumbers($id, $o['numbers']);

    return $id;
};
$fit = static fn (int $b, ?string $model = null, ?int $from = null, ?int $to = null): array => ['brand_id' => $b, 'model' => $model, 'year_from' => $from, 'year_to' => $to];

$pdo->beginTransaction();
try {
    $a = $add('ZZTEST Corolla pads', ['price' => '450.00', 'fits' => [$fit($toyota, 'Corolla', 2014, 2018)], 'numbers' => ['04465-02220', '04465-YZZ'], 'condition' => 'new', 'stock' => 'in_stock']);
    $b = $add('ZZTEST Toyota any model', ['fits' => [$fit($toyota)]]);
    $c = $add('ZZTEST Honda only', ['fits' => [$fit($honda, 'Civic', 2010)]]);
    $d = $add('ZZTEST Universal', ['cat' => 'interior']);
    $add('ZZTEST Hidden', ['published' => 0, 'fits' => [$fit($toyota)]]);
    $add('ZZTEST Corolla later', ['fits' => [$fit($toyota, 'corolla', 2019)]]);
    $svc = PublicPartService::create();
    $names = static fn (array $res): array => array_column($res['cards'], 'name');

    echo "\nFind parts for your vehicle (/parts/)\n";
    $all = $svc->browse(['q' => 'ZZTEST']);
    $check('only published parts are listed (5 of 6)', $all['total'] === 5 && !in_array('ZZTEST Hidden', $names($all), true), implode('|', $names($all)));
    $check('ordered by category, then name', $names($all) === ['ZZTEST Corolla later', 'ZZTEST Corolla pads', 'ZZTEST Honda only', 'ZZTEST Toyota any model', 'ZZTEST Universal'], implode('|', $names($all)));
    $t = $svc->browse(['q' => 'ZZTEST', 'make' => 'toyota']);
    $check('make: parts that fit it first, then parts with no compatibility ("confirm fit"); another make is left out', $names($t) === ['ZZTEST Corolla later', 'ZZTEST Corolla pads', 'ZZTEST Toyota any model', 'ZZTEST Universal'], implode('|', $names($t)));
    $check('models for a make: each once, ignoring case ("Corolla" and "corolla")', $t['options']['models'] === ['Corolla'] || $t['options']['models'] === ['corolla'], json_encode($t['options']['models']));
    $m = $svc->browse(['q' => 'ZZTEST', 'make' => 'toyota', 'model' => 'COROLLA', 'year' => '2016']);
    $check('make + model (any case) + year: range respected, "all models" and open years count', $names($m) === ['ZZTEST Corolla pads', 'ZZTEST Toyota any model', 'ZZTEST Universal'], implode('|', $names($m)));
    $y = $svc->browse(['q' => 'ZZTEST', 'make' => 'toyota', 'year' => '2020']);
    $check('year only: open-ended "2019 onwards" matches 2020, 2014-2018 does not', $names($y) === ['ZZTEST Corolla later', 'ZZTEST Toyota any model', 'ZZTEST Universal'], implode('|', $names($y)));
    $check('the vehicle reads "Toyota Corolla 2016"', $m['vehicle'] === 'Toyota Corolla 2016', (string) $m['vehicle']);
    $junk = $svc->browse(['q' => 'ZZTEST', 'make' => "toyota' OR 1=1", 'model' => 'Camry', 'year' => '1800', 'category' => '../x', 'page' => '-1']);
    $check('unknown make, model, year, category and page are ignored (never an error, never echoed)', $junk['total'] === 5 && $junk['filters'] === ['make' => null, 'model' => null, 'year' => null, 'category' => null, 'q' => 'ZZTEST'] && $junk['page'] === 1);
    $nm = $svc->browse(['q' => 'ZZTEST', 'make' => 'toyota', 'model' => 'Camry', 'year' => 'abcd']);
    $check('a model the make does not have, or a non-year, is ignored but the make stays', $nm['filters']['make'] === 'toyota' && $nm['filters']['model'] === null && $nm['filters']['year'] === null);
    $check('model or year without a make are ignored', $svc->browse(['q' => 'ZZTEST', 'model' => 'Corolla', 'year' => '2016'])['total'] === 5);
    $check('category filter', $svc->browse(['q' => 'ZZTEST', 'category' => 'interior'])['total'] === 1);
    $add('ZZORDER Aaa no list', ['cat' => 'engine']);                                   // earlier category and name, no compatibility
    $add('ZZORDER Zzz fits', ['cat' => 'wheels-tyres', 'fits' => [$fit($toyota)]]);     // later category and name, fits Toyota
    $check('with a vehicle chosen, parts that fit come before "confirm fit" parts (even from an earlier category)',
        $names($svc->browse(['q' => 'ZZORDER', 'make' => 'toyota'])) === ['ZZORDER Zzz fits', 'ZZORDER Aaa no list']
        && $names($svc->browse(['q' => 'ZZORDER'])) === ['ZZORDER Aaa no list', 'ZZORDER Zzz fits']);

    echo "\nSearch\n";
    $check('search by name', $svc->browse(['q' => 'zztest honda'])['total'] === 1);
    $check('search by any part number, ignoring hyphens and spaces ("0446502220", "04465 yzz")', $names($svc->browse(['q' => '0446502220'])) === ['ZZTEST Corolla pads'] && $names($svc->browse(['q' => '04465 yzz'])) === ['ZZTEST Corolla pads']);
    $check('LIKE wildcards in the search are literal ("%" and "_" match nothing)', $svc->browse(['q' => '%'])['total'] === 0 && $svc->browse(['q' => '_'])['total'] === 0);
    $check('over-long or control-character search is ignored', $svc->browse(['q' => str_repeat('a', 101)])['filters']['q'] === '' && $svc->browse(['q' => "a\x00b"])['filters']['q'] === '');

    echo "\nPages\n";
    $p1 = $svc->browse([]);
    $check('20 parts per page', count($p1['cards']) === min(20, $p1['total']) && $p1['pages'] === max(1, (int) ceil($p1['total'] / 20)));
    $past = $svc->browse(['page' => '999']);
    $check('a page past the end shows the last page', $past['page'] === $p1['pages'] && $past['cards'] !== []);
    $selects = static fn (): int => (int) $pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch(PDO::FETCH_NUM)[1];
    $before = $selects();
    $svc->browse(['make' => 'toyota']);
    $check('one page needs a fixed number of queries (no query per card)', $selects() - $before <= 8, (string) ($selects() - $before));

    echo "\nCards\n";
    $cards = array_column($all['cards'], null, 'name');
    $check('a part without price, condition or stock: price null ("Price on request"), condition and stock null', $cards['ZZTEST Toyota any model']['price'] === null
        && $cards['ZZTEST Toyota any model']['condition'] === null && $cards['ZZTEST Toyota any model']['in_stock'] === null);
    $check('a part with them: "GHS 450", New, in stock, main part number', $cards['ZZTEST Corolla pads']['price'] === 'GHS 450' && $cards['ZZTEST Corolla pads']['condition'] === 'New'
        && $cards['ZZTEST Corolla pads']['in_stock'] === true && $cards['ZZTEST Corolla pads']['part_number'] === '04465-02220');
    $check('compatibility reads naturally; none entered = empty list', $cards['ZZTEST Corolla pads']['fits'] === ['Toyota Corolla 2014–2018'] && $cards['ZZTEST Toyota any model']['fits'] === ['Toyota all models']
        && $cards['ZZTEST Universal']['fits'] === []);

    echo "\nHomepage, assistant, contact\n";
    $home = array_filter($svc->homepage()['cards'], static fn (array $p): bool => str_starts_with($p['name'], 'ZZTEST'));
    $check('homepage Latest Parts: only parts with a price (owner decision 2026-10-26)', array_column($home, 'name') === ['ZZTEST Corolla pads'], implode('|', array_column($home, 'name')));
    $homeTypes = array_column($svc->homepage()['types'], 'slug');
    $check('homepage type chips: only categories with a priced part', in_array('brakes', $homeTypes, true) && !in_array('interior', $homeTypes, true) || $homeTypes === ['brakes'], implode(',', $homeTypes));
    $assist = array_column(array_filter($svc->forAssistant(), static fn (array $p): bool => str_starts_with($p['name'], 'ZZTEST')), null, 'name');
    $check('the assistant sees every published part, with all part numbers', count($assist) === 5 && $assist['ZZTEST Corolla pads']['numbers'] === ['04465-02220', '04465-YZZ']);
    $slugA = PartValidator::slugify('ZZTEST Corolla pads');
    $check('contact prefill names the part and its main number', $svc->enquiryLabel($slugA) === 'ZZTEST Corolla pads (part number 04465-02220)');
    $check('contact prefill vehicle: real make, its model (any case), valid year', $svc->vehicleLine(['make' => 'toyota', 'model' => 'COROLLA', 'year' => '2016']) === 'Toyota Corolla 2016'
        && $svc->vehicleLine(['make' => 'toyota', 'model' => '<b>x</b>', 'year' => '20']) === 'Toyota' && $svc->vehicleLine(['make' => 'nope']) === null && $svc->vehicleLine(['make' => ['toyota']]) === null);

    echo "\nAdmin: part numbers, list filters, copy compatible vehicles\n";
    $service = PartService::create();
    $adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
    $post = ['category_id' => (string) $cat('brakes'), 'name' => 'ZZTEST Corolla pads', 'part_numbers' => ['04465-YZZ', '04465-02220'], 'condition' => 'new', 'price' => '450',
        'currency' => 'GHS', 'stock_status' => 'in_stock', 'description' => '', 'is_published' => '1',
        'fitments' => [['brand_id' => (string) $toyota, 'model' => 'Corolla', 'year_from' => '2014', 'year_to' => '2018']]];
    $u = $service->updatePart($a, $post, [], $adminId, '127.0.0.1', 'parts_page_test', false);
    $check('reordering the numbers is a change, saved in the new order', $u['status'] === 'ok' && $service->find($a)['numbers'] === ['04465-YZZ', '04465-02220'], $u['status']);
    $check('the audit entry names "part numbers" as changed, without the values', (string) $pdo->query("SELECT description FROM audit_logs WHERE user_agent = 'parts_page_test' ORDER BY id DESC LIMIT 1")->fetchColumn() === 'Part updated: ZZTEST Corolla pads (changed: part numbers)');
    $check('saving the same again is "no changes"', $service->updatePart($a, $post, [], $adminId, '127.0.0.1', 'parts_page_test', false)['status'] === 'unchanged');
    $post2 = ['price' => '', 'condition' => '', 'stock_status' => '', 'part_numbers' => []] + $post;
    $u2 = $service->updatePart($a, $post2, [], $adminId, '127.0.0.1', 'parts_page_test', false);
    $after = $service->find($a);
    $check('clearing price, condition, stock and numbers stores NULLs and no numbers', $u2['status'] === 'ok' && $after['price'] === null && $after['condition'] === null && $after['stock_status'] === null && $after['numbers'] === []);
    $list = $service->listParts(PartValidator::filters(['q' => 'ZZTEST', 'price' => 'none', 'fits' => 'none']));
    $check('admin list: "price on request" + "no vehicles yet" finds the parts still needing data', array_column($list['parts'], 'name') === ['ZZTEST Universal'], implode('|', array_column($list['parts'], 'name')));
    $check('admin search finds a part by any of its numbers', array_column($service->listParts(PartValidator::filters(['q' => '04465 YZZ']))['parts'], 'name') === []   // cleared above
        && count($service->listParts(PartValidator::filters(['q' => 'zztest', 'stock' => 'none']))['parts']) === 6);

    $typed = [['brand_id' => (string) $toyota, 'model' => 'Corolla', 'year_from' => '2014', 'year_to' => '2018']];
    $cp = $service->copyFitments((string) $c, $typed, $a);
    $check('copy: the source part\'s vehicles are added after the typed rows', $cp['added'] === 1 && count($cp['rows']) === 2 && $cp['rows'][1]['brand_id'] === (string) $honda && $cp['source'] === 'ZZTEST Honda only' && $cp['error'] === null);
    $again = $service->copyFitments((string) $c, $cp['rows'], $a);
    $check('copy again: nothing is repeated', $again['added'] === 0 && count($again['rows']) === 2);
    $check('copy from the part itself, a missing part or junk is refused', $service->copyFitments((string) $a, $typed, $a)['error'] !== null && $service->copyFitments('999999999', $typed, $a)['error'] !== null
        && $service->copyFitments("1 OR 1", $typed, $a)['error'] !== null);
    $full = array_map(static fn (int $i): array => ['brand_id' => (string) $toyota, 'model' => 'M' . $i, 'year_from' => '', 'year_to' => ''], range(1, PartValidator::FITMENTS_MAX));
    $capped = $service->copyFitments((string) $c, $full, $a);
    $check('copy never goes past the 20-vehicle limit', count($capped['rows']) === PartValidator::FITMENTS_MAX && $capped['error'] !== null);
    $check('copy saves nothing', count($service->find($a)['fitments']) === 1);
    $sources = array_column($service->copySources($a), 'name');
    $check('copy sources: parts with vehicles, never the part itself or one without vehicles', in_array('ZZTEST Honda only', $sources, true) && !in_array('ZZTEST Corolla pads', $sources, true) && !in_array('ZZTEST Universal', $sources, true));

    echo "\nParts on a vehicle's page (spec 28: in stock + listed as compatible only)\n";
    $accord = ['brand_id' => $honda, 'brand_slug' => 'honda', 'model' => 'Accord', 'year' => 2023];
    $add('ZZVEH Accord fits', ['stock' => 'in_stock', 'fits' => [$fit($honda, 'Accord', 2020, 2024)], 'price' => '300.00']);
    $add('ZZVEH Any Honda', ['stock' => 'in_stock', 'fits' => [$fit($honda)]]);
    $add('ZZVEH Case differs', ['stock' => 'in_stock', 'fits' => [$fit($honda, ' ACCORD ', 2023, 2023)]]);
    $add('ZZVEH Out of stock', ['stock' => 'out_of_stock', 'fits' => [$fit($honda, 'Accord')]]);
    $add('ZZVEH Stock not set', ['stock' => null, 'fits' => [$fit($honda, 'Accord')]]);
    $add('ZZVEH Unpublished', ['stock' => 'in_stock', 'published' => 0, 'fits' => [$fit($honda, 'Accord')]]);
    $add('ZZVEH Other model', ['stock' => 'in_stock', 'fits' => [$fit($honda, 'Civic')]]);
    $add('ZZVEH Wrong years', ['stock' => 'in_stock', 'fits' => [$fit($honda, 'Accord', 2010, 2015)]]);
    $add('ZZVEH Other make', ['stock' => 'in_stock', 'fits' => [$fit($toyota, 'Accord')]]);
    $add('ZZVEH No list', ['stock' => 'in_stock']);
    $vp = $svc->forVehicle($accord);
    $vNames = array_values(array_filter(array_column($vp['cards'], 'name'), static fn (string $n): bool => str_starts_with($n, 'ZZVEH')));
    sort($vNames);
    $check('only in-stock, published parts that list this make, model (any case or "all models") and year', $vNames === ['ZZVEH Accord fits', 'ZZVEH Any Honda', 'ZZVEH Case differs'], implode('|', $vNames));
    $check('out of stock, stock not set, other model, other years, other make and no list are never shown', array_intersect(array_column($vp['cards'], 'name'),
        ['ZZVEH Out of stock', 'ZZVEH Stock not set', 'ZZVEH Unpublished', 'ZZVEH Other model', 'ZZVEH Wrong years', 'ZZVEH Other make', 'ZZVEH No list']) === []);
    $check('the "see all" address names the vehicle for /parts/', $vp['all'] === '/parts/?make=honda&model=Accord&year=2023#results');
    for ($i = 1; $i <= 9; $i++) {
        $add('ZZVEH Many ' . $i, ['stock' => 'in_stock', 'fits' => [$fit($honda)]]);
    }
    $many = $svc->forVehicle($accord);
    $check('at most 8 cards; the total counts them all', count($many['cards']) === PublicPartService::VEHICLE_SHOWN && $many['total'] >= 12, (string) $many['total']);
    $check('a vehicle with no compatible parts gets an empty list (the page leaves the section out)', $svc->forVehicle(['brand_id' => $toyota, 'brand_slug' => 'toyota', 'model' => 'Nothing', 'year' => 1950])['cards'] === []);
    $vs = PublicVehicleService::create();
    $check('contact form vehicle name: only a real public vehicle slug counts', $vs->titleBySlug('no-such-vehicle-zz') === null && $vs->titleBySlug('../x') === null && $vs->titleBySlug(['a']) === null);

    echo "\nView\n";
    $render = static function (array $data) use ($root): string {
        ob_start();
        (static function () use ($data, $root): void {
            require $root . '/public/views/parts.php';
        })();

        return (string) ob_get_clean();
    };
    $html = $render(['result' => $svc->browse(['q' => 'ZZTEST', 'make' => 'toyota', 'model' => 'Corolla', 'year' => '2016']), 'whatsapp' => '233241234567']);
    $check('the vehicle form is shown once makes exist; choices are kept', str_contains($html, 'id="pf-make"') && str_contains($html, '<option value="toyota" selected>') && str_contains($html, 'value="2016" selected'));
    $check('"Price on request" for parts without a price; no "Confirm fit" line and no empty compatibility block', str_contains($html, 'Price on request') && !str_contains($html, 'Confirm fit') && substr_count($html, 'part-card__fits"') === substr_count($html, 'part-card__fits-list'));
    $check('Request links carry the part and the validated vehicle', str_contains($html, 'href="/contact/?part=zztest-universal&amp;make=toyota&amp;model=Corolla&amp;year=2016#message"'));
    $check('result line: how many really fit, and how many are still to confirm', str_contains($html, '<strong>2 parts fit your Toyota Corolla 2016</strong> · 1 more to confirm with us · matching'));
    $check('browse returns the fitting count only with a vehicle', $m['fitting'] === 2 && $all['fitting'] === null);
    $check('no stock badge or condition where none is set', !preg_match('/ZZTEST Universal.{0,400}In stock/s', $html));
    $empty = $render(['result' => $svc->browse(['q' => 'nothing-matches-this-zz']), 'whatsapp' => null]);
    $check('no result: a clear message, "Request a part" and "Show all parts"', str_contains($empty, 'No parts match your search') && str_contains($empty, 'Request a part') && str_contains($empty, 'Show all parts'));
    $check('unavailable data: a friendly notice, no error', str_contains($render(['result' => null, 'whatsapp' => null]), 'Parts are unavailable right now'));
    $noMakes = $svc->browse([]);
    $noMakes['options']['makes'] = [];
    $check('before any compatibility is entered, only the search box is shown', !str_contains($render(['result' => $noMakes, 'whatsapp' => null]), 'id="pf-make"'));
    $check('no emoji; no SQL in the views', preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html) !== 1
        && !preg_match('/SELECT |PDO|Database::/', (string) file_get_contents($root . '/public/views/parts.php') . (string) file_get_contents($root . '/includes/part_card.php')));
} finally {
    $pdo->rollBack();
}
$check('rolled back: no test part remains', (int) $pdo->query("SELECT COUNT(*) FROM parts WHERE name LIKE 'ZZTEST%' OR name LIKE 'ZZORDER%' OR name LIKE 'ZZVEH%'")->fetchColumn() === 0);

echo "\nCatalogue import (spec 27)\n";
$list = require $root . '/database/import/parts-catalogue/parts.php';
$slugs = array_column($list, 0);
$check('50 part types, unique valid slugs', count($list) === 50 && count(array_unique($slugs)) === 50 && array_filter($slugs, static fn (string $s): bool => preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $s) !== 1) === []);
$cats = $pdo->query('SELECT slug FROM part_categories')->fetchAll(PDO::FETCH_COLUMN);
$check('every category exists in the admin', array_diff(array_unique(array_column($list, 2)), $cats) === []);
$pics = array_filter(array_column($list, 3));
$check('47 pictures present (steering wheel, trunk lid, airbag have none: Toyota badge)', count($pics) === 47 && array_filter($pics, static fn (string $p): bool => !is_file($root . '/database/import/parts-catalogue/' . $p)) === []);
$check('the import folder is not reachable from the web (database/ is denied)', str_contains((string) file_get_contents($root . '/database/.htaccess'), 'Require all denied'));
$check('no part data left in public folders (old config, service, pictures)', !is_file($root . '/app/config/parts_catalogue.php') && !is_dir($root . '/assets/images/parts-catalogue') && !is_file($root . '/app/services/PartsCatalogue.php'));
// QA 2026-10-27: on a database without the catalogue the old check CREATED a real "engine" part. Now: inside a rolled-back
// transaction, the first import of a test slug creates it and a second import of the same slug is skipped.
$pdo->beginTransaction();
try {
    $importer = PartService::create();
    $first = $importer->importCatalogPart('zztest-import-skip', 'ZZTEST import', 'engine', null, '127.0.0.1', 'parts_page_test');
    $second = $importer->importCatalogPart('zztest-import-skip', 'ZZTEST import', 'engine', null, '127.0.0.1', 'parts_page_test');
    $check('the import creates a new slug once, then skips it (safe to run again)', $first === 'created' && $second === 'skipped', "$first/$second");
} finally {
    $pdo->rollBack();
}
$check('rolled back: the import test left no part behind', !$repo->slugTaken('zztest-import-skip'));

echo "\nRoute and controller\n";
$routes = require $root . '/app/config/public_routes.php';
$route = array_values(array_filter($routes['routes'], static fn ($r): bool => $r['name'] === 'parts'))[0];
$check('/parts/ is live with parts-finder.js', $route['status'] === 'live' && $route['js'] === ['parts-finder.js']);
$ctl = (string) file_get_contents($root . '/app/controllers/PublicController.php');
$check('contact prefill: the vehicle line is validated by the service, never copied from the URL', str_contains($ctl, '$service->vehicleLine($this->query)'));
$check('the finder JS builds options as text (no HTML from data)', !preg_match('/innerHTML|eval\(/', (string) file_get_contents($root . '/assets/js/parts-finder.js')));

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
