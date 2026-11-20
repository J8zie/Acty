<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Decides who may take, work on and rate a report.
class ReportPolicy
{

    // Checks if a volunteer may take this report.
    public function accept(User $user, Report $report): Response
    {
        return $user->isVolunteer() && $report->isPublic()
            ? Response::allow()
            : Response::deny('You are not allowed to do that.');
    }


    // Checks if this volunteer is the one working the task.
    public function work(User $user, Report $report): Response
    {
        return $user->isVolunteer() && $report->task?->volunteer_id === $user->id
            ? Response::allow()
            : Response::deny('This task belongs to another volunteer.');
    }


    // Checks if this resident filed the report and may rate it.
    public function rate(User $user, Report $report): Response
    {
        return $user->isResident() && $report->user_id === $user->id
            ? Response::allow()
            : Response::deny('Only the person who filed this report can rate it.');
    }
}
