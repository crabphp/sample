<?php

declare(strict_types=1);

namespace Tests;

use Crab\Testing\Api;
use Crab\Testing\ApiResponse;

/** A cookie jar using native form bodies; no browser scripting or session facility. */
final class Browser
{
    public array $cookies = [];

    public function __construct(
        private readonly Api $api,
    ) {}

    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->remember($this->api->withHeaders($this->headers())->get($path, $query));
    }

    public function post(string $path, array $form = []): ApiResponse
    {
        return $this->remember($this->api->withHeaders($this->headers())->post($path, form: $form));
    }

    public function headers(): array
    {
        $cookies = [];
        foreach ($this->cookies as $name => $value) {
            $cookies[] = $name . '=' . rawurlencode($value);
        }
        return ['Accept' => 'text/html', 'Sec-Fetch-Site' => 'same-origin', 'Cookie' => implode('; ', $cookies)];
    }

    public static function field(string $html, string $name): string
    {
        if (preg_match('/<input[^>]*name="' . preg_quote($name, '/') . '"[^>]*value="([^"]*)"/', $html, $match) !== 1) {
            throw new \LogicException('Missing form field: ' . $name);
        }
        return html_entity_decode($match[1], ENT_QUOTES);
    }

    public function add(int $id, int $quantity = 1): ApiResponse
    {
        $page = $this->get('/products/' . $id)->assertStatus(200);
        return $this->post('/cart/' . $id . '/add', [
            'actionId' => self::field($page->body(), 'actionId'),
            'quantity' => $quantity,
        ]);
    }

    private function remember(ApiResponse $response): ApiResponse
    {
        foreach ($response->response->getHeader('Set-Cookie') as $cookie) {
            [$name, $value] = explode('=', explode(';', $cookie, 2)[0], 2);
            if (str_contains($cookie, 'Max-Age=0')) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = rawurldecode($value);
            }
        }
        return $response;
    }
}
