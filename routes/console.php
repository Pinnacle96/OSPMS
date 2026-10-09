<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('notifications:expired-documents')->daily()->withoutOverlapping();
Schedule::command('ospm:tickets-expire')->daily()->withoutOverlapping();
