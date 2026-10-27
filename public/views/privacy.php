<?php

declare(strict_types=1);

/**
 * Privacy Policy (spec 29, owner request 2026-10-27). Linked from the footer only, not from the main menu.
 *
 * Every statement describes what this website really does; the figures come from the code that enforces them, so
 * the page cannot drift from the behaviour:
 *   CustomerPasswordResetService::VALID_MINUTES, CustomerVerificationService::LIFETIME_SECONDS,
 *   CookieConsentService::LIFETIME_DAYS, CustomerAuthService::MAX_LOGIN_FAILURES_PER_ACCOUNT.
 * The company's contact details come from Admin > Settings (left out when not set). Nothing here names a law,
 * a registration or a retention period the owner has not approved (see the spec's owner to-do list).
 *
 * @var array<string,mixed> $data contact (CompanyContactService::details()|null)
 */
$contact = $data['contact'] ?? null;
$email = $contact['email'] ?? null;
$address = $contact['address'] ?? null;
$resetMinutes = CustomerPasswordResetService::VALID_MINUTES;
$confirmHours = intdiv(CustomerVerificationService::LIFETIME_SECONDS, 3600);
$consentDays = CookieConsentService::LIFETIME_DAYS;
$maxFailures = CustomerAuthService::MAX_LOGIN_FAILURES_PER_ACCOUNT;
$sections = [
    'collect'  => 'Information we collect',
    'use'      => 'How we use it',
    'accounts' => 'Accounts, sign-in and passwords',
    'cookies'  => 'Cookies',
    'sharing'  => 'Services we work with',
    'keeping'  => 'How long we keep it',
    'choices'  => 'Your choices',
    'security' => 'How we protect it',
    'changes'  => 'Changes to this policy',
    'contact'  => 'Contact us',
];
?>
<?= public_page_header('Privacy Policy') ?>

<div class="container legal">
    <aside class="legal__toc" aria-labelledby="legal-toc-title">
        <h2 class="legal__toc-title" id="legal-toc-title">On this page</h2>
        <ol>
<?php foreach ($sections as $id => $title): ?>
            <li><a href="#<?= e($id) ?>"><?= e($title) ?></a></li>
<?php endforeach; ?>
        </ol>
    </aside>

    <article class="legal__body">
        <p class="legal__updated">Last updated: 27 October 2026</p>
        <p class="legal__lead">This policy explains what personal information <?= e(PUBLIC_SITE_NAME) ?> collects through this website, why, who else is involved, and the choices you have. We only collect what we need to answer you, run your account and keep you informed about your vehicle.</p>

        <section id="collect" aria-labelledby="collect-title">
            <h2 id="collect-title">Information we collect</h2>
            <h3>When you contact us or reserve a vehicle</h3>
            <p>Your name, email address, phone number (if you give it), your message, and the vehicle or part you ask about.</p>
            <h3>When you create an account</h3>
            <p>Your name, your email address and a password. If you choose <strong>Continue with Google</strong>, Google shares your name, email address and Google account identifier with us. We never receive your Google password.</p>
            <h3>When you use your account</h3>
            <p>The vehicles you save as favourites, when you last signed in, and the shipments linked to your account.</p>
            <h3>When we ship a vehicle for you</h3>
            <p>Your name and email address, the vehicle, the container number the shipping company gives us, and the shipment status.</p>
            <h3>Security records</h3>
            <p>When someone signs in, sends a form or asks for a password reset, we record the time, the IP address and the type of browser. We use these records only to protect the website and your account, for example to stop repeated attempts to guess a password.</p>
        </section>

        <section id="use" aria-labelledby="use-title">
            <h2 id="use-title">How we use it</h2>
            <ul>
                <li>To answer your questions and handle vehicle reservations and part requests. When you send a form, its details are emailed to our team so we can reply.</li>
                <li>To run your account: signing in, your favourites and your shipments.</li>
                <li>To email you when your shipment's status changes, and to send account emails such as email confirmation and password reset.</li>
                <li>To keep the website and your account secure.</li>
            </ul>
            <p>We do not sell your information, and we do not use it for advertising.</p>
        </section>

        <section id="accounts" aria-labelledby="accounts-title">
            <h2 id="accounts-title">Accounts, sign-in and passwords</h2>
            <ul>
                <li><strong>Passwords</strong> are stored only in a scrambled, one-way form (a hash). Nobody at <?= e(PUBLIC_SITE_NAME) ?> can read your password.</li>
                <li><strong>Email confirmation:</strong> the link we send works for <?= $confirmHours ?> hours.</li>
                <li><strong>Password reset:</strong> the link and the 6-digit code we email you work for <?= $resetMinutes ?> minutes and only once. They are kept in a scrambled form in short-term storage, not in our database, and are deleted as soon as they are used or expire.</li>
                <li><strong>Wrong passwords:</strong> after <?= $maxFailures ?> wrong passwords, signing in to that account is paused for a while. Password reset emails are also limited, to stop misuse.</li>
                <li><strong>Sessions:</strong> you stay signed in until you sign out, close your browser or are inactive for a while. Changing or resetting your password signs out every other device.</li>
            </ul>
        </section>

        <section id="cookies" aria-labelledby="cookies-title">
            <h2 id="cookies-title">Cookies</h2>
            <p>This website uses only essential cookies: to keep you signed in, to protect our forms against forgery and spam, and to remember your cookie choice (for <?= $consentDays ?> days). We do not use advertising, analytics or tracking cookies.</p>
            <p>The Contact page shows a map from Google Maps; while that page is open, Google may set its own cookies. The full list, and your settings, are on the <a href="/cookies/">Cookie settings</a> page.</p>
        </section>

        <section id="sharing" aria-labelledby="sharing-title">
            <h2 id="sharing-title">Services we work with</h2>
            <p>We share information only with the services needed to run this website, and only what each one needs:</p>
            <ul>
                <li><strong>Brevo</strong> sends our emails (account emails, shipment updates, and the copy of your form messages that goes to our team), so it receives the email addresses and the content of those emails.</li>
                <li><strong>Google</strong>, if you choose Continue with Google, and for the map on the Contact page.</li>
                <li><strong>WhatsApp</strong>, only when you choose to message us on WhatsApp: your message is then sent through WhatsApp.</li>
                <li><strong>Hapag-Lloyd</strong> and the <strong>import duty calculator</strong> are separate websites we link to. When you open them, their own privacy policies apply.</li>
            </ul>
        </section>

        <section id="keeping" aria-labelledby="keeping-title">
            <h2 id="keeping-title">How long we keep it</h2>
            <ul>
                <li>Password reset links and codes: <?= $resetMinutes ?> minutes at most.</li>
                <li>Email confirmation links: <?= $confirmHours ?> hours.</li>
                <li>Your cookie choice: <?= $consentDays ?> days, in your own browser.</li>
                <li>Your account, messages and shipment details: for as long as we need them to deal with your request, your purchase and your shipment, or until you ask us to delete them.</li>
            </ul>
        </section>

        <section id="choices" aria-labelledby="choices-title">
            <h2 id="choices-title">Your choices</h2>
            <ul>
                <li><strong>See or correct your details:</strong> you can change your name and password in <a href="/account/settings/">Account settings</a>. For anything else, contact us.</li>
                <li><strong>Delete your account or your messages:</strong> contact us and we will remove them, unless we must keep something to complete a purchase or shipment you are part of.</li>
                <li><strong>Cookies:</strong> change your choice at any time in <a href="/cookies/">Cookie settings</a>.</li>
            </ul>
        </section>

        <section id="security" aria-labelledby="security-title">
            <h2 id="security-title">How we protect it</h2>
            <p>Connections to this website are encrypted (HTTPS). Only our staff can see customer information, through a separate, password-protected staff area. Every change made by staff is recorded. Uploaded photos are checked and re-saved by our server, which removes hidden details such as the location they were taken.</p>
        </section>

        <section id="changes" aria-labelledby="changes-title">
            <h2 id="changes-title">Changes to this policy</h2>
            <p>If we change how we handle your information, we will update this page and the date at the top.</p>
        </section>

        <section id="contact" aria-labelledby="contact-title">
            <h2 id="contact-title">Contact us</h2>
            <p>For any question about your information, or to ask us to correct or delete it:</p>
            <ul class="legal__contact">
<?php if ($email !== null): ?>
                <li><?= public_icon('mail') ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
<?php endif; ?>
<?php if ($address !== null): ?>
                <li><?= public_icon('map-pin') ?><span><?= e($address) ?></span></li>
<?php endif; ?>
                <li><?= public_icon('message') ?><a href="/contact/">Contact page</a></li>
            </ul>
        </section>
    </article>
</div>
