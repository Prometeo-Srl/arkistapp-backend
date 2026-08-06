<?php

// api.php is pure wiring: every route group lives in routes/slices/*.php.
require __DIR__.'/slices/auth.php';
require __DIR__.'/slices/catalog.php';
require __DIR__.'/slices/tenancy.php';
require __DIR__.'/slices/documents.php';
require __DIR__.'/slices/checklists.php';
require __DIR__.'/slices/incidents.php';
require __DIR__.'/slices/support.php';
require __DIR__.'/slices/monitor.php';
require __DIR__.'/slices/stripe.php';
