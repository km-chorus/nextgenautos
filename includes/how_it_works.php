<?php

declare(strict_types=1);

/**
 * Homepage "How It Works" (spec 11A): an editorial five-step process, not five cards. An ordered list: a
 * horizontal process on wide screens, a vertical one below, joined by a thin line drawn in CSS. No JavaScript.
 * Presentation only; the wording lives in app/config/how_it_works.php.
 *
 * Expects: $router (PublicRouter) for the call-to-action URL.
 */

/** @var array{heading:string,intro:string,steps:list<array{title:string,text:string}>,cta:array{label:string,route:string}} $how */
$how = require dirname(__DIR__) . '/app/config/how_it_works.php';
$howCta = $router->byName($how['cta']['route']);
?>
<section class="process" id="how-it-works" aria-labelledby="process-title">
    <div class="container">
        <header class="process__head">
            <h2 class="process__title" id="process-title"><?= e($how['heading']) ?></h2>
            <p class="process__intro"><?= e($how['intro']) ?></p>
        </header>

        <ol class="process__steps" role="list">
<?php foreach ($how['steps'] as $i => $step): ?>
            <li class="process__step">
                <span class="process__number" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <h3 class="process__step-title"><?= e($step['title']) ?></h3>
                <p class="process__step-text"><?= e($step['text']) ?></p>
            </li>
<?php endforeach; ?>
        </ol>

<?php if ($howCta !== null): ?>
        <p class="process__cta"><a class="btn btn--secondary" href="<?= e($howCta['path']) ?>"><?= e($how['cta']['label']) ?></a></p>
<?php endif; ?>
    </div>
</section>
