<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\OpenApi\Attributes\Response;

final readonly class ShowProduct
{
    public function __construct(
        private Database $db,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Response(200, body: OpenApiSchemas::PRODUCT)]
    #[Response(404, description: 'No such product.')]
    public function handle(int $id): array
    {
        return $this->db->table('products')->find($id) ?? throw new HttpException(404, 'No such product.');
    }
}
