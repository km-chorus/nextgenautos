<?php

declare(strict_types=1);

/**
 * Parts and Accessories (spec 26; rebuilt for spec 27, 2026-10-26). Presentation only.
 *
 * Every published part from Admin > Parts (the 50 catalogue part types were imported there): "Find parts for your
 * vehicle" (make / model / year, plus search by name or any part number), category chips, 20 cards per page, and the
 * "not listed" band. A part shows only what the administrator entered: no price = "Price on request"; no compatible
 * vehicles = no compatibility block on the card (such parts are included in a vehicle search, so nothing is hidden by missing data).
 *
 * The form is a plain GET form (works without JavaScript); parts-finder.js fills the model list when a make is chosen.
 *
 * @var array<string,mixed> $data result (PublicPartService::browse()|null), whatsapp (?string)
 */
$result = $data['result'] ?? null;
$whatsappDigits = $data['whatsapp'] ?? null;
$whatsappGeneral = $whatsappDigits === null ? null
    : CompanyContactService::whatsappLink($whatsappDigits, "Hello, I'm looking for a car part.\nPart: \nMy vehicle (make, model, year): ");

if ($result !== null) {
    $f = $result['filters'];
    $o = $result['options'];
    // The current choices as a query (validated values only), for chips, pages and the cards' Request links.
    $vehicleQuery = array_filter(['make' => $f['make'], 'model' => $f['model'], 'year' => $f['year'] === null ? null : (string) $f['year']], static fn ($v): bool => $v !== null);
    $baseQuery = $vehicleQuery + array_filter(['q' => $f['q']], 'strlen');
    $url = static fn (array $query): string => '/parts/' . ($query === [] ? '' : '?' . http_build_query($query));
    $chipHref = static fn (?string $category): string => $url($baseQuery + ($category === null ? [] : ['category' => $category])) . '#results';
    $pageUrl = static fn (int $n): string => $url($baseQuery + array_filter(['category' => $f['category']], static fn ($v): bool => $v !== null) + ($n > 1 ? ['page' => (string) $n] : [])) . '#results';
    $pages = [];
    for ($n = 1; $n <= $result['pages']; $n++) {
        if ($n === 1 || $n === $result['pages'] || abs($n - $result['page']) <= 1) {
            $pages[] = $n;
        }
    }
    $filtered = $baseQuery !== [] || $f['category'] !== null;
    $vehicleLabel = $result['vehicle'];
    // Make / model / year appear once the admin has entered compatible vehicles for at least one published part.
    $byVehicle = $o['makes'] !== [];
}
?>
<?= public_page_header('Parts and Accessories') ?>

<section class="pf" aria-labelledby="pf-title">
    <div class="container">
<?php if ($result === null): ?>
        <div class="pf-unavailable" role="status">
            <h2 class="pf-title" id="pf-title">Parts are unavailable right now</h2>
            <p>Please try again shortly, or tell us the part you need and our team will help.</p>
        </div>
<?php else: ?>
        <form class="pf-finder" method="get" action="/parts/#results" role="search" aria-labelledby="pf-title" data-parts-finder data-models="<?= e((string) json_encode($o['modelsByMake'], JSON_UNESCAPED_UNICODE)) ?>">
            <h2 class="pf-title" id="pf-title"><?= $byVehicle ? 'Find parts for your vehicle' : 'Find a part' ?></h2>
<?php if ($f['category'] !== null): ?>
            <input type="hidden" name="category" value="<?= e($f['category']) ?>">
<?php endif; ?>
            <div class="pf-finder__grid<?= $byVehicle ? '' : ' pf-finder__grid--search' ?>">
<?php if ($byVehicle): ?>
                <div class="field">
                    <label class="label" for="pf-make">Make</label>
                    <select class="select" id="pf-make" name="make" data-finder-make>
                        <option value="">Any make</option>
<?php foreach ($o['makes'] as $m): ?>
                        <option value="<?= e($m['slug']) ?>"<?= $f['make'] === $m['slug'] ? ' selected' : '' ?>><?= e($m['name']) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="pf-model">Model</label>
                    <select class="select" id="pf-model" name="model" data-finder-model<?= $f['make'] === null ? ' disabled' : '' ?>>
                        <option value=""><?= $f['make'] === null ? 'Choose a make first' : 'All models' ?></option>
<?php foreach ($o['models'] as $model): ?>
                        <option value="<?= e($model) ?>"<?= $f['model'] === $model ? ' selected' : '' ?>><?= e($model) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="pf-year">Year</label>
                    <select class="select" id="pf-year" name="year">
                        <option value="">Any year</option>
<?php foreach ($o['years'] as $y): ?>
                        <option value="<?= (int) $y ?>"<?= $f['year'] === $y ? ' selected' : '' ?>><?= (int) $y ?></option>
<?php endforeach; ?>
                    </select>
                </div>
<?php endif; ?>
                <div class="field pf-finder__search">
                    <label class="label" for="pf-q">Part name or number</label>
                    <input class="input" type="search" id="pf-q" name="q" value="<?= e($f['q']) ?>" maxlength="<?= PublicPartService::SEARCH_MAX ?>" autocomplete="off" placeholder="For example: radiator">
                </div>
                <div class="pf-finder__actions">
                    <button class="btn btn--primary pf-finder__submit" type="submit"><?= public_icon('search') ?>Show parts</button>
<?php if ($filtered): ?>
                    <a class="pf-finder__clear" href="/parts/">Clear</a>
<?php endif; ?>
                </div>
            </div>
        </form>

<?php if ($o['categories'] !== []): ?>
        <nav class="pf-cats" aria-label="Part categories">
            <ul class="parts-chips">
                <li><a class="parts-chip" href="<?= e($chipHref(null)) ?>"<?= $f['category'] === null ? ' aria-current="true"' : '' ?>>All parts</a></li>
<?php foreach ($o['categories'] as $c): ?>
                <li><a class="parts-chip" href="<?= e($chipHref($c['slug'])) ?>"<?= $f['category'] === $c['slug'] ? ' aria-current="true"' : '' ?>><?= e($c['name']) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
<?php endif; ?>

        <div class="pf-results" id="results">
<?php if ($vehicleLabel !== null): $fits = (int) $result['fitting']; $confirm = $result['total'] - $fits; ?>
            <p class="pf-count" role="status">
                <strong><?= $fits ?> <?= $fits === 1 ? 'part fits' : 'parts fit' ?> your <?= e($vehicleLabel) ?></strong><?php if ($confirm > 0): ?> · <?= $confirm ?> more to confirm with us<?php endif; ?><?php if ($f['q'] !== ''): ?> · matching “<?= e($f['q']) ?>”<?php endif; ?>
            </p>
<?php if ($confirm > 0): ?>
            <p class="pf-note">Parts without a vehicle list yet are shown after the parts that fit. Request one and we check it fits your <?= e($vehicleLabel) ?>.</p>
<?php endif; ?>
<?php else: ?>
            <p class="pf-count" role="status">
                <strong><?= (int) $result['total'] ?> <?= $result['total'] === 1 ? 'part' : 'parts' ?></strong><?php if ($f['q'] !== ''): ?> matching “<?= e($f['q']) ?>”<?php endif; ?>
            </p>
<?php endif; ?>

<?php if ($result['cards'] === []): ?>
            <div class="pf-empty">
                <h3 class="pf-empty__title"><?= $vehicleLabel !== null ? 'No parts match your vehicle yet' : 'No parts match your search' ?></h3>
                <p class="pf-empty__text">Tell us the part and your vehicle, and our team will let you know if we can get it.</p>
                <div class="pf-empty__actions">
                    <a class="btn btn--primary" href="/contact/?<?= e(http_build_query(['request' => 'other'])) ?>#message">Request a part</a>
<?php if ($filtered): ?>
                    <a class="btn btn--outline" href="/parts/">Show all parts</a>
<?php endif; ?>
                </div>
            </div>
<?php else: ?>
            <ul class="parts-grid">
<?php foreach ($result['cards'] as $p): ?>
<?php $cardHidden = false; require dirname(__DIR__, 2) . '/includes/part_card.php'; ?>
<?php endforeach; ?>
            </ul>

<?php if ($result['pages'] > 1): ?>
            <nav class="pager" aria-label="Pagination">
<?php if ($result['page'] > 1): ?>
                <a class="pager__link" href="<?= e($pageUrl($result['page'] - 1)) ?>" rel="prev">Previous</a>
<?php endif; ?>
                <ol class="pager__pages">
<?php $last = 0; foreach ($pages as $n): ?>
<?php if ($n - $last > 1): ?>
                    <li class="pager__gap" aria-hidden="true">…</li>
<?php endif; $last = $n; ?>
<?php if ($n === $result['page']): ?>
                    <li><span class="pager__num is-current" aria-current="page"><?= $n ?></span></li>
<?php else: ?>
                    <li><a class="pager__num" href="<?= e($pageUrl($n)) ?>" aria-label="Page <?= $n ?>"><?= $n ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
                </ol>
<?php if ($result['page'] < $result['pages']): ?>
                <a class="pager__link" href="<?= e($pageUrl($result['page'] + 1)) ?>" rel="next">Next</a>
<?php endif; ?>
            </nav>
<?php endif; ?>
<?php endif; ?>
        </div>
<?php endif; ?>
    </div>
</section>

<section class="pc-cta" aria-labelledby="pc-cta-title">
    <div class="container">
        <div class="pc-cta__box">
            <div>
                <h2 class="pc-cta__title" id="pc-cta-title">Looking for a part that is not listed?</h2>
                <p class="pc-cta__text">Tell us the part and your vehicle, and our team will let you know if we can get it.</p>
            </div>
            <div class="pc-cta__actions">
                <a class="btn btn--primary pc-cta__btn" href="/contact/?<?= e(http_build_query(['request' => 'other'])) ?>#message">Send a request<?= public_icon('arrow-right') ?></a>
<?php if ($whatsappGeneral !== null): ?>
                <a class="btn pc-cta__btn pc-cta__btn--ghost" href="<?= e($whatsappGeneral) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('message') ?>WhatsApp<span class="sr-only"> (opens in a new tab)</span></a>
<?php endif; ?>
            </div>
        </div>
    </div>
</section>
