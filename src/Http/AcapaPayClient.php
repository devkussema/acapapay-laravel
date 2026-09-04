<?php

namespace AcapaPay\Laravel\Http;

use AcapaPay\Laravel\Exceptions\ApiException;
use AcapaPay\Laravel\Exceptions\AuthenticationException;
use AcapaPay\Laravel\Exceptions\ConnectionException;
use AcapaPay\Laravel\Exceptions\ForbiddenException;
use AcapaPay\Laravel\Exceptions\NotFoundException;
use AcapaPay\Laravel\Exceptions\ValidationException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP único do SDK: trata do token OAuth2 (com cache), dos timeouts,
 * das retentativas e da tradução de erros HTTP em excepções tipadas.
 *
 * Toda a comunicação com o AcapaPay passa por aqui — o AcapaPayManager, a API
 * de pagamento direta e o comando acapapay:test-connection partilham este
 * cliente e, portanto, a mesma cache de token.
 *
 * @since 1.2.0
 */
class AcapaPayClient
{
    /**
     * Chave de cache do token. Mantida exactamente igual à das versões
     * anteriores para que um deploy a meio não force uma reautenticação.
     */
    public function cacheKey(): string
    {
        return 'acapapay_oauth_token_' . md5((string) config('acapapay.client_id'));
    }

    /**
     * Obtém um token OAuth2 (Client Credentials), reutilizando o que está em cache.
     *
     * @param bool $fresh Ignora a cache e força um token novo.
     *
     * @throws AuthenticationException
     * @throws ConnectionException
     */
    public function token(bool $fresh = false): string
    {
        if ($fresh) {
            $this->forgetToken();
        }

        $ttl = (int) config('acapapay.token_ttl', 3000);

        $token = Cache::remember($this->cacheKey(), $ttl, function () {
            try {
                $response = $this->baseRequest()
                    ->asForm()
                    ->post(config('acapapay.host') . '/oauth/token', [
                        'grant_type'    => 'client_credentials',
                        'client_id'     => config('acapapay.client_id'),
                        'client_secret' => config('acapapay.client_secret'),
                    ]);
            } catch (HttpConnectionException $e) {
                throw new ConnectionException(
                    'AcapaPay SDK: Não foi possível contactar o servidor de identidade. ' . $e->getMessage()
                );
            }

            if (!$response->successful()) {
                // A excepção impede que um token falhado fique em cache.
                throw new AuthenticationException(
                    'AcapaPay SDK: Falha na autenticação OAuth. Verifica as tuas credenciais no .env.'
                );
            }

            $accessToken = $response->json('access_token');

            if (!is_string($accessToken) || $accessToken === '') {
                throw new AuthenticationException(
                    'AcapaPay SDK: O servidor de identidade respondeu sem access_token.'
                );
            }

            return $accessToken;
        });

        return (string) $token;
    }

    public function forgetToken(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * Executa um pedido autenticado à API do AcapaPay.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $query
     * @return array<mixed>
     *
     * @throws ApiException|AuthenticationException|ConnectionException
     */
    public function request(string $method, string $uri, array $data = [], array $query = []): array
    {
        $response = $this->send($method, $uri, $data, $query, $this->token());

        // 401: o token pode ter expirado antes do TTL da cache. Repetimos uma
        // única vez com um token novo — antes, o SDK limpava a cache mas
        // deixava este pedido falhar na mesma.
        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $this->send($method, $uri, $data, $query, $this->token());
        }

        if (!$response->successful()) {
            throw $this->toException($response, $method, $uri);
        }

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $uri, [], $query);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    public function post(string $uri, array $data = []): array
    {
        return $this->request('POST', $uri, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    public function put(string $uri, array $data = []): array
    {
        return $this->request('PUT', $uri, $data);
    }

    /**
     * Verifica a conectividade com a API (usado pelo acapapay:test-connection).
     *
     * @return array<mixed>
     */
    public function ping(): array
    {
        return $this->get('/v1/ping');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $query
     */
    protected function send(string $method, string $uri, array $data, array $query, string $token): Response
    {
        $url = config('acapapay.api_host') . $uri;

        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $request = $this->baseRequest()->withToken($token);

        try {
            // GET não leva corpo — evita enviar um Content-Type: application/json
            // com "{}", que alguns proxies/WAF rejeitam.
            if (strtoupper($method) === 'GET') {
                return $request->get($url);
            }

            return $request->send($method, $url, ['json' => $data]);
        } catch (HttpConnectionException $e) {
            throw new ConnectionException(
                'AcapaPay SDK: Falha de comunicação com o servidor de pagamento. ' . $e->getMessage()
            );
        }
    }

    /**
     * Cliente HTTP base, com timeouts e retentativas configuráveis.
     */
    protected function baseRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions(['verify' => config('acapapay.verify_ssl')])
            ->acceptJson()
            ->timeout((int) config('acapapay.timeout', 30))
            ->connectTimeout((int) config('acapapay.connect_timeout', 10))
            ->retry(
                (int) config('acapapay.retry_times', 2),
                (int) config('acapapay.retry_sleep', 200),
                // Só repetir falhas de rede e erros 5xx. Um 422 nunca deve ser repetido.
                function ($exception, $request) {
                    return $exception instanceof HttpConnectionException;
                },
                throw: false
            );
    }

    /**
     * Traduz uma resposta de erro na excepção tipada correspondente.
     */
    protected function toException(Response $response, string $method, string $uri): ApiException
    {
        $status = $response->status();
        $body = $response->body();
        $json = $response->json() ?? [];
        $apiError = is_array($json) ? ($json['error'] ?? $json['message'] ?? null) : null;

        Log::error('AcapaPay SDK Erro API: ' . $body, [
            'method' => $method,
            'uri' => $uri,
            'status' => $status,
        ]);

        // Prefixo mantido igual ao das versões anteriores, para não partir
        // alertas/greps de log já existentes nas apps satélite.
        $message = 'AcapaPay SDK: Falha na comunicação com o servidor de pagamento. Status: ' . $status;

        if ($apiError) {
            $message .= ' — ' . $apiError;
        }

        return match (true) {
            $status === 422 => new ValidationException(
                $message,
                is_array($json) ? ($json['errors'] ?? []) : [],
                $status,
                $body,
                $apiError,
                $method,
                $uri
            ),
            $status === 404 => new NotFoundException($message, $status, $body, $apiError, $method, $uri),
            $status === 403 => new ForbiddenException($message, $status, $body, $apiError, $method, $uri),
            default => new ApiException($message, $status, $body, $apiError, $method, $uri),
        };
    }
}
