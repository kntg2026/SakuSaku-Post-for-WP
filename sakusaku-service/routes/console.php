<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tenants:process-expired-trials')->daily();
