<?php

declare(strict_types=1);

/**
 * Validation of the public vehicle enquiry and reservation forms (spec 14). The server is the only enforcement point; the
 * browser's checks are a convenience. Returns cleaned values plus field errors; never throws.
 *
 * Rules: name 2-150 characters (no control characters or angle brackets); email valid, up to 255; phone optional for an
 * enquiry and required for a reservation, 7-40 characters of digits, spaces, + ( ) - . ; message 10-2000 characters,
 * required for an enquiry and optional for a reservation (a standard line is stored when it is left empty).
 */
final class EnquiryValidator
{
    public const KINDS = ['contact', 'reserve'];   // the two forms of a vehicle page; the Contact page is kind 'general' (spec 17)
    /** Contact page topics (spec 17): enquiry_type => label. The browser sends the key; anything else is refused. */
    public const TOPICS = ['general' => 'General question', 'vehicle' => 'Buying a vehicle', 'part' => 'Car parts', 'shipping' => 'Shipping & tracking'];
    public const MAX_NAME = 150;
    public const MAX_EMAIL = 255;
    public const MAX_PHONE = 40;
    public const MIN_MESSAGE = 10;
    public const MAX_MESSAGE = 2000;
    public const DEFAULT_RESERVE_MESSAGE = 'I would like to reserve this vehicle.';

    /**
     * @param array<string,mixed> $post
     * @param string $kind 'contact' | 'reserve' (vehicle page) | 'general' (Contact page, also needs a topic)
     * @return array{values:array{name:string,email:string,phone:?string,message:string,topic?:string},errors:array<string,string>}
     */
    public static function validate(string $kind, array $post): array
    {
        $reserve = $kind === 'reserve';
        $name = is_string($post['name'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $post['name']) ?? '') : '';
        $email = is_string($post['email'] ?? null) ? strtolower(trim($post['email'])) : '';
        $phone = is_string($post['phone'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $post['phone']) ?? '') : '';
        $message = is_string($post['message'] ?? null) ? trim(str_replace(["\r\n", "\r"], "\n", $post['message'])) : '';

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Enter your name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > self::MAX_NAME || preg_match('/[\p{Cc}<>]/u', $name) === 1) {
            $errors['name'] = 'Use 2 to ' . self::MAX_NAME . ' characters, without special symbols.';
        }

        if ($email === '') {
            $errors['email'] = 'Enter your email address.';
        } elseif (strlen($email) > self::MAX_EMAIL || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($phone === '') {
            if ($reserve) {
                $errors['phone'] = 'Enter a phone number so we can call you to confirm the reservation.';
            }
        } elseif (strlen($phone) < 7 || strlen($phone) > self::MAX_PHONE || preg_match('/^[0-9+()\-.\s]+$/', $phone) !== 1
            || preg_match_all('/[0-9]/', $phone) < 7) {
            $errors['phone'] = 'Enter a valid phone number (digits, spaces and + only).';
        }

        if ($message === '' && $reserve) {
            $message = self::DEFAULT_RESERVE_MESSAGE;
        } elseif ($message === '') {
            $errors['message'] = 'Enter your message.';
        } elseif (mb_strlen($message) < self::MIN_MESSAGE || mb_strlen($message) > self::MAX_MESSAGE) {
            $errors['message'] = 'Use ' . self::MIN_MESSAGE . ' to ' . self::MAX_MESSAGE . ' characters.';
        } elseif (preg_match('/[^\P{Cc}\n\t]/u', $message) === 1) {
            $errors['message'] = 'Remove unusual control characters from your message.';
        }

        $values = ['name' => $name, 'email' => $email, 'phone' => $phone === '' ? null : $phone, 'message' => $message];
        if ($kind === 'general') {
            $topic = is_string($post['topic'] ?? null) ? $post['topic'] : '';
            if (!isset(self::TOPICS[$topic])) {
                $errors['topic'] = 'Choose what your message is about.';
                $topic = '';
            }
            $values['topic'] = $topic;
        }

        return ['values' => $values, 'errors' => $errors];
    }
}
