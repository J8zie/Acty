<?php

namespace App\Rules;

// Shared text patterns and messages for names and usernames.
final class Patterns
{

    // Allowed characters for a person's name.
    public const NAME = '/^[\p{L}\p{M}][\p{L}\p{M}\s.\'’-]*$/u';


    // Allowed characters for a username.
    public const USERNAME = '/^[A-Za-z0-9_]+$/';

    // The error message shown when a name has bad characters.
    public const NAME_MESSAGE = 'may only contain letters, spaces, dots, apostrophes and hyphens.';
}
