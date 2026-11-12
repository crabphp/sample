<?php

declare(strict_types=1);

namespace App\Models;

use Crab\Database\ColumnName;
use Crab\Database\Json;
use Crab\Tide\Hidden;
use Crab\Tide\Model;
use Crab\Tide\Table;
use Crab\Tide\Timestamps;

#[Table('account_preferences', key: 'user_id')]
#[Timestamps(created: null)]
final class Preferences extends Model
{
    #[Hidden]
    #[ColumnName('user_id')]
    public int $accountId;
    public bool $newsletter = false;
    /** @var list<string> */
    #[Json]
    public array $categories = [];
    public ?\DateTimeImmutable $updated_at = null;
}
