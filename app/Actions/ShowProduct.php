<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;

final readonly class ShowProduct
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(int $id): array
    {
        return $this->db->table('products')->find($id) ?? throw new HttpException(404, 'No such product.');
    }
}
