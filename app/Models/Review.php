<?php

declare(strict_types=1);

namespace App\Models;

use Crab\Tide\BelongsTo;
use Crab\Tide\Model;
use Crab\Tide\MorphTo;

final class Review extends Model
{
    public int $id;
    public int $user_id;
    public string $reviewable_type;
    public int $reviewable_id;
    public int $rating;
    public string $body;
    public ?\DateTimeImmutable $created_at;
    public ?\DateTimeImmutable $updated_at;

    #[BelongsTo('user_id')]
    public Account $author;

    #[MorphTo]
    public Product|Order $reviewable;
}
