<?php

declare(strict_types=1);

namespace App\Actions;

use App\Shop\BrowserCookies;
use App\Shop\Carts;
use App\Shop\Pages;
use App\User;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Request;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\OpenApi\Attributes\Security;
use Crab\Routing\FromQuery;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Length;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;

#[ApiResponse(403, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(422, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(401, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(404, body: ['type' => 'string'], contentType: 'text/html')]
final readonly class ShopAssistant
{
    public function __construct(
        private AskAssistant $ask,
        private ChatWithAssistant $chat,
        private Pages $pages,
        private Database $db,
        private ClockInterface $clock,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    public function show(Request $request, ?User $user, #[FromQuery] ?string $reply = null): Response
    {
        $answer = null;
        $products = [];
        if ($reply !== null) {
            $answer = $this->db
                ->table('assistant_replies')
                ->where('id', $reply)
                ->where('owner', $this->owner($request, $user))
                ->where('expires_at', '>', $this->clock->now()->getTimestamp())
                ->first() ?? throw new HttpException(404, 'This answer is no longer available.');
            $ids = json_decode($answer['products'], true, flags: JSON_THROW_ON_ERROR);
            $products = $ids === [] ? [] : $this->db->table('products')->whereIn('id', $ids)->get();
        }
        return $this->pages->render('shop.assistant', $request, $user, ['answer' => $answer, 'products' => $products]);
    }

    #[ApiResponse(303, headers: ['Location', 'Set-Cookie'])]
    public function handle(
        #[Fields(['question' => ['string', new Length(1, 200)]])]
        Data $input,
        Request $request,
        ?User $user,
    ): ResponseInterface {
        $reply = $this->ask->handle($input, $request);
        if ($reply instanceof ResponseInterface) {
            return $reply;
        }
        $answer = '';
        $products = [];
        foreach ($reply as $event) {
            $answer .= $event['token'] ?? '';
            $products = $event['products'] ?? $products;
        }
        return $this->keep($input, $request, $user, ['answer' => $answer, 'products' => $products, 'turn' => 0]);
    }

    #[ApiResponse(303, headers: ['Location'])]
    #[Security('cookie')]
    public function chat(
        #[Fields(['question' => ['string', new Length(1, 200)]])]
        Data $input,
        Request $request,
        User $user,
    ): ResponseInterface {
        $reply = $this->chat->handle($user, $input, $request);
        return $reply instanceof ResponseInterface ? $reply : $this->keep($input, $request, $user, $reply);
    }

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/event-stream')]
    public function stream(
        #[Fields(['question' => ['string', new Length(1, 200)]])]
        Data $input,
        Request $request,
    ): \Generator|ResponseInterface {
        return $this->ask->handle($input, $request);
    }

    private function owner(Request $request, ?User $user): string
    {
        return $user === null ? 'guest:' . BrowserCookies::read($request, 'shop_assistant') : 'user:' . $user->id;
    }

    private function keep(Data $input, Request $request, ?User $user, array $reply): Response
    {
        $guest = BrowserCookies::read($request, 'shop_assistant');
        if (preg_match('/^[a-f0-9]{64}$/D', $guest) !== 1) {
            $guest = bin2hex(random_bytes(32));
        }
        $id = Carts::uuid();
        $this->db
            ->table('assistant_replies')
            ->insert([
                'id' => $id,
                'owner' => $user === null ? 'guest:' . $guest : 'user:' . $user->id,
                'question' => $input->question,
                'answer' => $reply['answer'],
                'turn' => $reply['turn'],
                'products' => json_encode($reply['products'], JSON_THROW_ON_ERROR),
                'expires_at' => $this->clock->now()->getTimestamp() + 86_400,
            ]);
        $response = Response::redirect('/assistant?reply=' . $id, 303)->withHeader(
            'Cache-Control',
            'private, no-store',
        );
        return $user === null
            ? $response->withCookie(BrowserCookies::make('shop_assistant', $guest, 86_400))
            : $response;
    }
}
