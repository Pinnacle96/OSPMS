<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ospm:tickets-expire')->daily()->withoutOverlapping();
