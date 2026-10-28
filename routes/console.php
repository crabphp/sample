<?php

declare(strict_types=1);

use App\Console\CancelStaleOrders;
use App\Console\DispatchOrders;
use App\Console\MirrorCatalogue;
use App\Console\Seed;
use App\Console\Traffic;
use Crab\Command;

Command::add('shop:seed', Seed::class);
Command::add('shop:traffic', Traffic::class);
Command::add('orders:cancel-stale', CancelStaleOrders::class);
Command::add('shop:mirror-catalogue', MirrorCatalogue::class);
Command::add('shop:dispatch', DispatchOrders::class);
