<?php

declare(strict_types=1);

namespace App;

enum TagOperation: string
{
    case Attach = 'attach';
    case Detach = 'detach';
    case Sync = 'sync';
}
