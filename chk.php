<?php

use App\Models\User;

echo 'ok-never exists: '.User::where('email', 'ok-never@example.com')->count()."\n";
echo 'a@x.com exists: '.User::where('email', 'a@x.com')->count()."\n";
echo 'marta-bulk exists: '.User::where('email', 'marta-bulk@example.com')->count()."\n";
