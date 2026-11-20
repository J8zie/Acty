<?php

namespace App\Rules;

/**
 * The text patterns shared by several forms. The Angular app uses the same ones in
 * src/app/lib/validators.ts, so a mistake is caught in the browser first and, if somebody skips the browser,
 * here again.
 */
final class Patterns
{
    /** Letters (any language), spaces, dots, apostrophes and hyphens: "Juan Dela Cruz", "O'Brien", "Anne-Marie". */
    public const NAME = '/^[\p{L}\p{M}][\p{L}\p{M}\s.\'’-]*$/u';

    /** Letters, digits and underscore. */
    public const USERNAME = '/^[A-Za-z0-9_]+$/';

    public const NAME_MESSAGE = 'may only contain letters, spaces, dots, apostrophes and hyphens.';
}
