<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may do what to ONE report. (The route's `role:` middleware already decided who may even try; this checks
 * the record: "is it THEIR report / THEIR task?".)
 */
class ReportPolicy
{
    /** A volunteer may take a report the public can see. */
    public function accept(User $user, Report $report): Response
    {
        return $user->isVolunteer() && $report->isPublic()
            ? Response::allow()
            : Response::deny('You are not allowed to do that.');
    }

    /** Start / complete: only the volunteer who holds the task. */
    public function work(User $user, Report $report): Response
    {
        return $user->isVolunteer() && $report->task?->volunteer_id === $user->id
            ? Response::allow()
            : Response::deny('This task belongs to another volunteer.');
    }

    /** Rate: only the resident who filed it (guests, volunteers and admins cannot). */
    public function rate(User $user, Report $report): Response
    {
        return $user->isResident() && $report->user_id === $user->id
            ? Response::allow()
            : Response::deny('Only the person who filed this report can rate it.');
    }
}
