<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Http\PreferenceInput;
use App\Models\Account;
use App\Models\Preferences;
use App\Models\Product;
use App\User;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\OpenApi\Attributes\Response;

final readonly class AccountPreferences
{
    public function __construct(
        private Database $db,
    ) {}

    #[Response(200, body: OpenApiSchemas::PREFERENCES)]
    public function handle(User $user): Preferences
    {
        $account = Account::query()->with('preferences')->find($user->id);
        return $account->preferences ?? new Preferences();
    }

    #[Response(200, body: OpenApiSchemas::PREFERENCES)]
    #[Response(422, description: 'Choose categories from the catalogue.')]
    public function save(User $user, PreferenceInput $input): Preferences
    {
        $categories = array_values(array_unique($input->categories));
        $known = Product::query()->whereIn('category', $categories)->pluck('category');
        if (array_diff($categories, $known) !== []) {
            throw new HttpException(422, 'Choose categories from the catalogue.');
        }
        return $this->db->transaction(static function () use ($user, $input, $categories): Preferences {
            // Serialize first-time preference creation on the existing account row.
            $account = Account::query()->lockForUpdate()->with('preferences')->find($user->id);
            $preferences = $account->preferences ?? new Preferences();
            $preferences->accountId = $account->id;
            $preferences->newsletter = $input->newsletter;
            $preferences->categories = $categories;
            $preferences->save();
            return $preferences;
        });
    }
}
