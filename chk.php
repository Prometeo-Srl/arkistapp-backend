<?php
echo "ok-never exists: ".\App\Models\User::where('email','ok-never@example.com')->count()."\n";
echo "a@x.com exists: ".\App\Models\User::where('email','a@x.com')->count()."\n";
echo "marta-bulk exists: ".\App\Models\User::where('email','marta-bulk@example.com')->count()."\n";
