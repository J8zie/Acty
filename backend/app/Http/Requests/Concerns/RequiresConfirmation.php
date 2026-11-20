<?php

namespace App\Http\Requests\Concerns;

// Shared rules and messages for forms that need a confirm tick.
trait RequiresConfirmation
{

    // The rule that requires the confirm box to be ticked.
    protected function confirmationRules(): array
    {
        return ['confirm' => ['required', 'accepted']];
    }


    // The error messages for the confirm box.
    protected function confirmationMessages(): array
    {
        return [
            'confirm.required' => 'Please confirm this action.',
            'confirm.accepted' => 'Please confirm this action.',
        ];
    }
}
