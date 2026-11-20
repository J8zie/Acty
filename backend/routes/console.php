<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('actify:purge-rejected-guest-reports')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
