<?php

declare(strict_types=1);

/**
 * Contact page (spec 17_spec_contact_page.md). Presentation only; every value is escaped here.
 * Business details come from the admin Settings (CompanyContactService::details): anything not filled in is not shown.
 *
 * @var array<string,mixed> $data  details (phone, whatsapp, email, hours, socials), form (values, errors, message), sent (bool),
 *                                 prefill (name, email), token (string|null), whatsapp (wa.me link|null)
 */
$d = $data['details'];
$form = $data['form'];
$phones = array_values(array_filter([$d['phone'], $d['phone2'] ?? null]));   // main number first; the same number twice is shown once
if (count($phones) === 2 && $phones[0]['tel'] === $phones[1]['tel']) {
    array_pop($phones);
}
$hasDetails = $data['whatsapp'] !== null || $phones !== [] || $d['email'] !== null || $d['hours'] !== null || $d['socials'] !== [];
$posted = $form['message'] !== null;
$value = static fn (string $name): string => $posted ? (string) ($form['values'][$name] ?? '') : (string) ($data['prefill'][$name] ?? '');
$error = static fn (string $name): ?string => $form['errors'][$name] ?? null;
$field = static fn (string $name, string $label, string $type, bool $required, string $autocomplete, array $options = []): string
    => public_enquiry_field('message', $name, $label, $type, $required, $autocomplete, $value($name), $error($name), '', $options);
?>
<?= public_page_header('Contact Us', 'Get in touch', 'Questions about a vehicle, car parts or shipping to Ghana? Send us a message or chat with us on WhatsApp.') ?>

<div class="container contact">
    <section class="contact__details" aria-labelledby="details-title">
        <h2 class="contact__heading" id="details-title">Contact details</h2>
<?php if (!$hasDetails): ?>
        <p class="contact__soon">Our contact details will be available here soon. You can send us a message with the form.</p>
<?php else: ?>
<?php if ($data['whatsapp'] !== null): ?>
        <a class="btn contact__whatsapp" href="<?= e($data['whatsapp']) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('message') ?><span>Chat with us on WhatsApp</span><span class="sr-only"> (opens WhatsApp in a new tab)</span></a>
<?php endif; ?>
<?php if ($phones !== [] || $d['email'] !== null || $d['hours'] !== null): ?>
        <dl class="contact__list">
<?php foreach ($phones as $phone): ?>
            <div class="contact__item">
                <dt><?= public_icon('phone') ?><span>Phone</span></dt>
                <dd><a href="tel:<?= e($phone['tel']) ?>"><?= e($phone['display']) ?></a></dd>
            </div>
<?php endforeach; ?>
<?php if ($d['email'] !== null): ?>
            <div class="contact__item">
                <dt><?= public_icon('mail') ?><span>Email</span></dt>
                <dd><a href="mailto:<?= e($d['email']) ?>"><?= e($d['email']) ?></a></dd>
            </div>
<?php endif; ?>
<?php if ($d['hours'] !== null): ?>
            <div class="contact__item">
                <dt><?= public_icon('clock') ?><span>Opening hours</span></dt>
                <dd class="contact__hours"><?= nl2br(e($d['hours'])) ?></dd>
            </div>
<?php endif; ?>
        </dl>
<?php endif; ?>
<?php if ($d['socials'] !== []): ?>
        <h3 class="contact__subheading">Follow us</h3>
        <ul class="contact__socials">
<?php foreach ($d['socials'] as $social): ?>
            <li><a href="<?= e($social['url']) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon($social['key']) ?><span><?= e($social['label']) ?></span><span class="sr-only"> (opens in a new tab)</span></a></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
<?php endif; ?>
    </section>

    <section class="contact__form" id="message" aria-labelledby="message-title">
        <h2 class="contact__heading" id="message-title">Send us a message</h2>
<?php if ($data['sent']): ?>
        <div class="alert alert--success" role="status"><?= public_icon('check-circle') ?><p>Thank you. We have received your message and will reply soon.</p></div>
<?php elseif ($data['token'] === null): ?>
        <p class="contact__soon">Online messages are unavailable right now.<?= $hasDetails ? ' Please use the contact details on this page.' : ' Please try again later.' ?></p>
<?php else: ?>
<?php if ($posted): ?>
        <div class="alert alert--error" role="alert"><?= public_icon('alert-circle') ?><p><?= e($form['message']) ?></p></div>
<?php endif; ?>
        <form class="form contact__fields" method="post" action="/contact/#message" novalidate data-guard-form>
            <?= public_enquiry_hidden('message', 'general', (string) $data['token']) ?>
            <div class="contact__row">
                <?= $field('name', 'Full name', 'text', true, 'name') ?>
                <?= $field('email', 'Email address', 'email', true, 'email') ?>
            </div>
            <div class="contact__row">
                <?= $field('phone', 'Phone number', 'tel', false, 'tel') ?>
                <?= $field('topic', 'Topic', 'select', true, '', ['' => 'Choose a topic'] + EnquiryValidator::TOPICS) ?>
            </div>
            <?= $field('message', 'Message', 'textarea', true, '') ?>
            <button class="btn btn--primary" type="submit">Send message</button>
        </form>
<?php endif; ?>
    </section>
</div>
