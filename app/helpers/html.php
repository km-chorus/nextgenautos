<?php

declare(strict_types=1);

/**
 * Escape a value for output in HTML text or a quoted HTML attribute.
 * Use for every dynamic value rendered into a template.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
