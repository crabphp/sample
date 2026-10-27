<?php

declare(strict_types=1);

namespace App\Console;

use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;
use Crab\Contracts\HttpClient\Client;

/**
 * Downloads the "supplier's" catalogue feed into a file: this
 * application's own /products.csv, standing in for someone else's.
 *
 * The body goes straight into the file as it arrives, the file appears
 * only once it is all there, a feed larger than it should be is given up
 * as soon as that is known, and the bytes are kept exactly as sent, so
 * that their hash is the supplier's.
 */
final readonly class MirrorCatalogue implements Command
{
    /**
     * A feed bigger than this is not the catalogue.
     */
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private Client $http)
    {
    }

    public function description(): string
    {
        return <<<'TEXT'
            Download the catalogue feed into storage/catalogue.csv
            Options:
              --url=http://127.0.0.1:8080   Where the shop is; shop.warehouse_url when left out
            TEXT;
    }

    public function handle(Input $input, Output $output): int
    {
        $url = rtrim((string) $input->option('url', (string) config('shop.warehouse_url')), '/');
        if ($url === '') {
            $output->error('There is no shop to download from: give --url= or set WAREHOUSE_URL.');
            return 1;
        }
        $file = (string) config('shop.catalogue');

        $this->http->timeout(120)->request('GET', "{$url}/products.csv",
            sink: $file,
            maxBytes: self::MAX_BYTES,
            decode: false,
        )->throw();

        $output->line(sprintf('Saved %s: %d bytes, sha256 %s.', $file, filesize($file), hash_file('sha256', $file)));
        return 0;
    }
}
