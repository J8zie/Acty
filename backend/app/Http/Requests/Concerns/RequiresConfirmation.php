<?php

namespace App\Http\Requests\Concerns;

/**
 * "Are you sure?" enforced by the server.
 *
 * The Angular app asks the person to confirm important actions (delete, cancel, give a rating...) in a dialog, then
 * sends `confirm: true` with the request. The server insists on it too, so an action that cannot be undone never
 * happens by a stray click, a script, or an old/buggy client that forgot to ask.
 */
trait RequiresConfirmation
{
    /** @return array<string, array<int, string>> */
    protected function confirmationRules(): array
    {
        return ['confirm' => ['required', 'accepted']];
    }

    /** @return array<string, string> */
    protected function confirmationMessages(): array
    {
        return [
            'confirm.required' => 'Please confirm this action.',
            'confirm.accepted' => 'Please confirm this action.',
        ];
    }
}
