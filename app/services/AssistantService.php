<?php

declare(strict_types=1);

require_once __DIR__ . '/AssistantData.php';

/**
 * The site assistant (spec 19_spec_site_assistant.md): a guided chat WITHOUT AI. It recognises a question (a quick-reply
 * id, or typed text matched by keywords and by the makes / models / years really listed) and answers ONLY from the
 * site's real data (AssistantData) and its fixed business rules. It never invents a price, delivery time, duty amount,
 * container number, address or service; when it has no answer it says so and hands over to the team.
 *
 * reply() returns plain data for JSON: ['messages' => list<message>, 'chips' => list<{id,label}>], where a message is
 * ['text' => string, 'lines' => list<string>, 'numbered' => bool, 'items' => list<{title,meta,price,href}>,
 *  'links' => list<{label,href,external}>]. All text is plain (the browser inserts it with textContent).
 */
final class AssistantService
{
    public const MAX_LENGTH = 200;
    /** Same name as the public site (PUBLIC_SITE_NAME in public_ui.php), used in the greeting. */
    public const SITE_NAME = 'Next Gen Autos and Parts';
    public const LIST_LIMIT = 5;

    /** Quick replies: id => label (no emojis, per the design rules). */
    public const CHIPS = [
        'vehicles' => 'Vehicles', 'prices' => 'Prices', 'process' => 'How buying works', 'shipping' => 'Shipping & tracking',
        'duty' => 'Import duty', 'parts' => 'Car parts', 'contact' => 'Contact us', 'hours' => 'Opening hours',
    ];

    /** Every intent an answer exists for (also accepted as ?intent=). */
    public const INTENTS = ['start', 'vehicles', 'prices', 'reserve', 'process', 'shipping', 'tracking', 'duty', 'parts', 'contact', 'hours',
        'location', 'payment', 'account', 'about', 'brands', 'test_drive', 'thanks', 'human'];

    /** Keyword => intent. Phrases are matched on word boundaries in the lower-cased question. */
    private const KEYWORDS = [
        'thanks'     => ['thank', 'thanks', 'thank you', 'thx', 'appreciate'],
        'human'      => ['human', 'agent', 'real person', 'speak to someone', 'talk to someone', 'representative', 'staff'],
        'test_drive' => ['test drive', 'testdrive', 'drive it', 'see the car', 'inspect', 'inspection', 'viewing', 'view the car'],
        'payment'    => ['pay', 'payment', 'payments', 'momo', 'mobile money', 'bank', 'card', 'finance', 'financing', 'loan', 'installment', 'instalment', 'credit', 'deposit'],
        'tracking'   => ['track', 'tracking', 'container', 'where is my', 'container number', 'shipment status'],
        'duty'       => ['duty', 'duties', 'customs', 'tax', 'taxes', 'clearing', 'clearance', 'import duty'],
        'shipping'   => ['ship', 'shipping', 'shipped', 'deliver', 'delivery', 'arrive', 'arrival', 'how long', 'port', 'tema', 'takoradi', 'freight'],
        'parts'      => ['part', 'parts', 'spare', 'spares', 'accessory', 'accessories', 'brake', 'brakes', 'filter', 'headlight', 'bumper', 'radiator', 'engine part', 'tyre', 'tyres', 'tire', 'tires'],
        'reserve'    => ['reserve', 'reservation', 'book', 'buy', 'purchase', 'order', 'hold'],
        'process'    => ['how it works', 'how does it work', 'process', 'steps', 'procedure', 'import a car', 'import a vehicle', 'how to buy', 'how do i buy'],
        'prices'     => ['price', 'prices', 'cost', 'costs', 'how much', 'cheap', 'cheapest', 'expensive', 'ghs', 'cedis', 'budget', 'affordable'],
        'hours'      => ['hours', 'open', 'opening', 'close', 'closing', 'closed', 'working hours', 'weekend', 'saturday', 'sunday', 'today'],
        'location'   => ['location', 'located', 'address', 'where are you', 'office', 'showroom', 'visit', 'directions', 'map'],
        'contact'    => ['contact', 'phone', 'call', 'number', 'email', 'e-mail', 'whatsapp', 'reach', 'talk', 'speak', 'message'],
        'account'    => ['account', 'sign in', 'signin', 'log in', 'login', 'register', 'sign up', 'signup', 'password'],
        'about'      => ['about', 'who are you', 'company', 'amponsah', 'next gen', 'nextgen', 'trust', 'legit', 'genuine business'],
        'brands'     => ['brands', 'makes', 'which brands', 'which makes'],
        'vehicles'   => ['vehicle', 'vehicles', 'car', 'cars', 'available', 'stock', 'inventory', 'suv', 'sedan', 'truck', 'pickup', 'jeep', 'van', 'listing', 'listings'],
        'start'      => ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'akwaaba', 'start', 'menu', 'help'],
    ];

    public function __construct(private AssistantData $data)
    {
    }

    public static function create(): self
    {
        return new self(new AssistantData());
    }

    /** Cleans typed text: control characters removed, spaces collapsed, at most MAX_LENGTH characters. Null when empty. */
    public static function cleanQuestion(mixed $text): ?string
    {
        if (!is_string($text)) {
            return null;
        }
        $text = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '' || !mb_check_encoding($text, 'UTF-8')) {
            return null;
        }

        return mb_substr($text, 0, self::MAX_LENGTH);
    }

    /** @return array{messages:list<array<string,mixed>>,chips:list<array{id:string,label:string}>} */
    public function reply(mixed $intent, mixed $question): array
    {
        if (is_string($intent) && in_array($intent, self::INTENTS, true)) {
            return $this->answer($intent, null);
        }
        $q = self::cleanQuestion($question);
        if ($q === null) {
            return $this->answer('start', null);
        }

        return $this->answerText($q);
    }

    /** Which intent a question is about (null = not recognised). Public for the tests. */
    public static function detect(string $question): ?string
    {
        $text = ' ' . strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $question) ?? '') . ' ';
        $best = null;
        $bestScore = 0;
        foreach (self::KEYWORDS as $intent => $words) {
            $score = 0;
            foreach ($words as $word) {
                if (str_contains($text, ' ' . $word . ' ')) {
                    $score += str_contains($word, ' ') ? 2 : 1;   // a phrase is a stronger signal than one word
                }
            }
            if ($score > $bestScore) {   // ties keep the earlier (more specific) intent in KEYWORDS
                $best = $intent;
                $bestScore = $score;
            }
        }

        return $best;
    }

    // ---- typed questions ------------------------------------------------------------------------------------------------

    private function answerText(string $q): array
    {
        $intent = self::detect($q);
        $words = self::words($q);

        // Parts first when the question is about parts and names something we can match.
        if ($intent === 'parts') {
            return $this->answer('parts', $words);
        }
        // A make / model / year that is really listed answers with those vehicles (with prices), unless the question is
        // clearly about something else (tracking, duty, shipping, payment ...).
        if (in_array($intent, [null, 'vehicles', 'prices', 'reserve', 'start', 'brands'], true)) {
            $matches = $this->matchVehicles($words);
            if ($matches !== null) {
                return $matches;
            }
            $unlisted = $this->unlistedBrand($words);
            if ($unlisted !== null) {
                return $unlisted;
            }
        }

        return $intent === null ? $this->fallback() : $this->answer($intent, $words);
    }

    // ---- answers --------------------------------------------------------------------------------------------------------

    /** @param list<string>|null $words the typed question's words (parts matching), null for a quick reply */
    private function answer(string $intent, ?array $words): array
    {
        return match ($intent) {
            'start'      => $this->out([self::msg("I'm the " . self::SITE_NAME . ' assistant, an automated helper that answers from the information on this website. Choose a topic or type your question.')],
                array_keys(self::CHIPS)),
            'vehicles'   => $this->vehicleList(false),
            'prices'     => $this->vehicleList(true),
            'reserve'    => $this->out([self::msg('To reserve a vehicle, open its page and press "Reserve this vehicle". Send your name, phone number and email, and our team will call you to confirm. The vehicle is reserved for you once we confirm it. No payment is taken on the website.',
                links: [self::link('Browse vehicles', '/vehicles/')])], ['vehicles', 'payment', 'contact']),
            'process'    => $this->process(),
            'shipping'   => $this->out([self::msg('After you buy a vehicle, we arrange its shipment to Ghana. The shipping company assigns a container and gives us its number; we send that number to you, and you follow the container on the shipping line\'s official tracking site. Shipping times are not listed on the website: our team can tell you what to expect for your vehicle.',
                links: [self::link('Track a vehicle', '/track/'), ...$this->whatsappLink('Ask about shipping')])], ['tracking', 'duty', 'contact']),
            'tracking'   => $this->tracking(),
            'duty'       => $this->out([self::msg('This website does not calculate Ghana import duty. You can estimate it with the external duty calculator, using the vehicle\'s details (make, model, year and so on). Listed prices do not include duty unless the vehicle says "Duty included".',
                links: [self::link('Open the duty calculator', $this->data->dutyUrl(), true)])], ['prices', 'shipping', 'contact']),
            'parts'      => $this->parts($words ?? []),
            'contact'    => $this->contact('Here is how to reach us:'),
            'human'      => $this->contact("I'm an automated assistant. To talk to a person on our team:"),
            'hours'      => $this->hours(),
            'location'   => $this->contact('Our address is not listed on the website yet. Please contact us and we will help you:'),
            'payment'    => $this->out([self::msg('No payment is taken on this website, and financing is not offered through it. Please discuss payment with our team once you have chosen a vehicle.',
                links: $this->whatsappLink('Ask our team'))], ['reserve', 'contact']),
            'account'    => $this->out([self::msg('With a customer account you can sign in and open the Track Vehicle page. Creating one only needs your name, email and a password.',
                links: [self::link('Create an account', '/register/'), self::link('Sign in', '/login/')])], ['tracking', 'contact']),
            'about'      => $this->out([self::msg('Next Gen Autos and Parts operates between Canada and Ghana: we source quality vehicles in Canada, ship them to Ghana, and also deal in car parts for popular makes such as Toyota, Honda and Hyundai.',
                links: [self::link('Browse vehicles', '/vehicles/')])], ['vehicles', 'process', 'contact']),
            'brands'     => $this->brands(),
            'test_drive' => $this->contact('Test drives cannot be booked on the website. Each vehicle page has photos and full details, and our team can answer your questions:'),
            'thanks'     => $this->out([self::msg("You're welcome! Is there anything else I can help with?")], ['vehicles', 'parts', 'contact']),
            default      => $this->fallback(),
        };
    }

    private function vehicleList(bool $prices): array
    {
        $cards = $this->data->vehicles();
        if ($cards === null) {
            return $this->unavailable('Vehicles');
        }
        if ($cards === []) {
            return $this->contact('No vehicles are listed on the website right now. Our team can tell you what is coming:');
        }
        $text = ($prices ? 'Current prices' : 'Vehicles available now') . ' (' . count($cards) . ' listed' . (count($cards) > self::LIST_LIMIT ? ', newest first' : '') . '):';

        return $this->out([self::msg($text, items: array_map([self::class, 'vehicleItem'], array_slice($cards, 0, self::LIST_LIMIT)),
            lines: $prices ? ['Prices do not include Ghana import duty unless a vehicle says "Duty included".'] : [],
            links: [self::link('View all vehicles', '/vehicles/')])], $prices ? ['duty', 'reserve', 'contact'] : ['prices', 'reserve', 'parts']);
    }

    /** Vehicles whose make, model or year is named in the question, or null when the question names none. */
    private function matchVehicles(array $words): ?array
    {
        $cards = $this->data->vehicles();
        if ($cards === null || $cards === [] || $words === []) {
            return null;
        }
        $found = [];
        foreach ($cards as $card) {
            $vocab = self::words($card['brand'] . ' ' . $card['name'] . ' ' . $card['year']);
            $hits = array_intersect($words, $vocab);
            if ($hits !== []) {
                $found[] = ['card' => $card, 'score' => count($hits)];
            }
        }
        if ($found === []) {
            return null;
        }
        $top = max(array_column($found, 'score'));
        $cards = array_column(array_filter($found, static fn (array $f): bool => $f['score'] === $top), 'card');

        return $this->out([self::msg(count($cards) === 1 ? 'I found this vehicle:' : 'I found ' . count($cards) . ' matching vehicles:',
            items: array_map([self::class, 'vehicleItem'], array_slice($cards, 0, self::LIST_LIMIT)),
            links: [self::link('View all vehicles', '/vehicles/')])], ['reserve', 'duty', 'contact']);
    }

    /** A known make (active brand) that has no vehicle listed right now. */
    private function unlistedBrand(array $words): ?array
    {
        foreach ($this->data->brands() ?? [] as $brand) {
            if (array_intersect(self::words($brand), $words) !== []) {
                return $this->contact('We have no ' . $brand . ' listed on the website right now. Ask our team about it:');
            }
        }

        return null;
    }

    private function process(): array
    {
        $steps = $this->data->steps();

        return $this->out([self::msg('How buying a vehicle works:', lines: array_map(static fn (array $s): string => $s['title'] . ': ' . $s['text'], $steps), numbered: true,
            links: [self::link('Browse vehicles', '/vehicles/')])], ['reserve', 'shipping', 'duty']);
    }

    private function tracking(): array
    {
        return $this->out([self::msg('Tracking uses the real container number from the shipping line. Once your vehicle is assigned to a shipment, we send you that number. Enter it on the official tracking site to see where your container is. Until then there is nothing to track yet. Sign in to open the Track Vehicle page.',
            links: [self::link('Track Vehicle page', '/track/'), self::link('Official tracking site', $this->data->trackingUrl(), true)])], ['shipping', 'account', 'contact']);
    }

    private function parts(array $words): array
    {
        $cards = $this->data->parts();
        if ($cards === null) {
            return $this->unavailable('Parts');
        }
        if ($cards === []) {
            return $this->contact('Our parts catalogue is coming soon. Tell us the part and your vehicle, and our team will help:');
        }
        $ignore = ['part', 'parts', 'spare', 'spares', 'accessory', 'accessories', 'for', 'the', 'and', 'do', 'you', 'have', 'sell', 'any', 'need', 'my', 'car', 'a', 'an', 'i', 'is', 'there'];
        $wanted = array_values(array_diff($words, $ignore));
        $matched = $wanted === [] ? [] : array_values(array_filter($cards, static function (array $p) use ($wanted): bool {
            $vocab = self::words($p['name'] . ' ' . $p['type'] . ' ' . implode(' ', $p['fits']) . ' ' . implode(' ', $p['makes']) . ' ' . implode(' ', $p['numbers'] ?? [$p['part_number'] ?? '']));
            foreach ($wanted as $w) {
                if (in_array($w, $vocab, true) || in_array(rtrim($w, 's'), $vocab, true)) {
                    return true;
                }
            }

            return false;
        }));
        if ($wanted !== [] && $matched === []) {
            return $this->contact("I couldn't find that part in our listed parts. Our team may still be able to help:");
        }
        $list = $matched !== [] ? $matched : $cards;
        $items = array_map(static fn (array $p): array => [
            'title' => $p['name'],
            // Spec 27: condition and stock only when the administrator set them; no price = "Price on request".
            'meta'  => implode(' · ', array_filter([$p['type'], $p['condition'], $p['in_stock'] === null ? null : ($p['in_stock'] ? 'In stock' : 'Out of stock'),
                'Fits: ' . ($p['fits'] === [] ? 'ask us' : implode(', ', $p['fits']) . ($p['fits_more'] > 0 ? ' and ' . $p['fits_more'] . ' more' : ''))])),
            'price' => $p['price'] ?? 'Price on request',
            'href'  => '/contact/?' . http_build_query(['part' => $p['slug']]) . '#message',
        ], array_slice($list, 0, self::LIST_LIMIT));

        return $this->out([self::msg($matched !== [] ? 'Parts that match:' : 'Our latest parts and accessories:', items: $items,
            lines: ['Choose a part to ask us about it.'], links: [self::link('See all parts', '/parts/')])], ['contact', 'vehicles']);
    }

    private function hours(): array
    {
        $c = $this->data->contact();
        if ($c !== null && $c['hours'] !== null) {
            return $this->out([self::msg('Our opening hours:', lines: array_values(array_filter(array_map('trim', explode("\n", $c['hours'])))),
                links: $this->contactLinks($c))], ['contact', 'location']);
        }

        return $this->contact('Our opening hours are not listed on the website yet. You can reach us here:');
    }

    private function brands(): array
    {
        $brands = $this->data->brands();
        if ($brands === null || $brands === []) {
            return $this->answer('vehicles', null);
        }

        return $this->out([self::msg('Makes we source include: ' . implode(', ', $brands) . '. Ask me about one, for example "' . $brands[0] . '".',
            links: [self::link('Browse vehicles', '/vehicles/')])], ['vehicles', 'prices']);
    }

    private function contact(string $intro): array
    {
        $c = $this->data->contact();

        return $this->out([self::msg($intro, links: $c === null ? [self::link('Contact page', '/contact/')] : $this->contactLinks($c))], ['hours', 'vehicles', 'parts']);
    }

    /** @param array{phones:list<array{display:string,tel:string}>,whatsapp:?string,email:?string} $c */
    private function contactLinks(array $c): array
    {
        $links = [];
        if ($c['whatsapp'] !== null) {
            $links[] = self::link('Chat on WhatsApp', $c['whatsapp'], true);
        }
        foreach ($c['phones'] as $phone) {
            $links[] = self::link('Call ' . $phone['display'], 'tel:' . $phone['tel']);
        }
        if ($c['email'] !== null) {
            $links[] = self::link('Email ' . $c['email'], 'mailto:' . $c['email']);
        }
        $links[] = self::link('Send us a message', '/contact/');

        return $links;
    }

    private function whatsappLink(string $label): array
    {
        $c = $this->data->contact();

        return $c !== null && $c['whatsapp'] !== null ? [self::link($label, $c['whatsapp'], true)] : [self::link('Send us a message', '/contact/')];
    }

    private function fallback(): array
    {
        $out = $this->contact("Sorry, I couldn't find an answer to that on the website. I'm an automated assistant; our team can help:");
        $out['chips'] = self::chips(['vehicles', 'prices', 'process', 'parts']);

        return $out;
    }

    private function unavailable(string $what): array
    {
        return $this->contact($what . ' could not be loaded right now. Please try again shortly, or contact us:');
    }

    // ---- helpers --------------------------------------------------------------------------------------------------------

    /** @return list<string> lower-case words (letters and digits) */
    private static function words(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($parts === false ? [] : $parts));
    }

    private static function vehicleItem(array $card): array
    {
        return ['title' => $card['title'], 'meta' => $card['status'] . ($card['duty'] ? ' · Duty included' : ''), 'price' => $card['price'], 'href' => $card['url']];
    }

    private static function msg(string $text, array $items = [], array $lines = [], array $links = [], bool $numbered = false): array
    {
        return ['text' => $text, 'lines' => $lines, 'numbered' => $numbered, 'items' => $items, 'links' => $links];
    }

    private static function link(string $label, string $href, bool $external = false): array
    {
        return ['label' => $label, 'href' => $href, 'external' => $external];
    }

    /** @param list<string> $chipIds */
    private function out(array $messages, array $chipIds): array
    {
        return ['messages' => $messages, 'chips' => self::chips($chipIds)];
    }

    private static function chips(array $ids): array
    {
        $labels = self::CHIPS + ['reserve' => 'How to reserve', 'tracking' => 'Tracking', 'payment' => 'Payment', 'account' => 'My account', 'location' => 'Location'];

        return array_values(array_map(static fn (string $id): array => ['id' => $id, 'label' => $labels[$id]], array_filter($ids, static fn (string $id): bool => isset($labels[$id]))));
    }
}
