<?php

namespace App\Services\Integrations\Security;

use App\Models\Integrations\IntegrationClient;
use App\Models\Integrations\IntegrationClientKey;
use App\Services\Integrations\Security\Contracts\JwtCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class IntegrationJwtVerifier
{
    public function __construct(
        private readonly JwtCodec $codec,
        private readonly IntegrationJtiService $jtiService,
    ) {
    }

    public function verify(Request $request, string $requiredScope): IntegrationAuthContext
    {
        $token = $this->extractBearerToken($request);
        if ($token === null) {
            throw new AuthenticationException('Missing integration bearer token.');
        }

        $header = $this->decodeSegment($this->segment($token, 0), 'header');
        $unverifiedPayload = $this->decodeSegment($this->segment($token, 1), 'payload');

        $kid = trim((string) ($header['kid'] ?? ''));
        $alg = trim((string) ($header['alg'] ?? ''));
        $issuer = trim((string) ($unverifiedPayload['iss'] ?? ''));

        if ($alg !== 'RS256' || $kid === '' || $issuer === '') {
            throw new AuthenticationException('Malformed integration JWT.');
        }

        $client = IntegrationClient::query()
            ->where('client_code', $issuer)
            ->where('status', IntegrationClient::STATUS_ACTIVE)
            ->first();

        if (! $client) {
            throw new AuthenticationException('Unknown integration client.');
        }

        $key = IntegrationClientKey::query()
            ->where('client_id', $client->id)
            ->where('kid', $kid)
            ->where('status', IntegrationClientKey::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->first();

        if (! $key) {
            throw new AuthenticationException('Unknown integration key.');
        }

        try {
            $claims = $this->codec->decode($token, $key->public_key, ['RS256']);
        } catch (Throwable $exception) {
            throw new AuthenticationException('Invalid integration JWT signature or payload.', previous: $exception);
        }

        $audience = trim((string) ($claims['aud'] ?? ''));
        $subject = ($claims['sub'] ?? null);
        $jti = trim((string) ($claims['jti'] ?? ''));
        $iat = (int) ($claims['iat'] ?? 0);
        $exp = (int) ($claims['exp'] ?? 0);
        $scopes = $this->normalizeScopes($claims['scope'] ?? []);

        if (($claims['iss'] ?? null) !== $issuer) {
            throw new AuthenticationException('Invalid integration issuer.');
        }

        if ($audience !== (string) config('integrations.inbound.audience')) {
            throw new AuthenticationException('Invalid integration audience.');
        }

        $this->assertValidLifetime($iat, $exp);

        if ($jti === '') {
            throw new AuthenticationException('Missing integration jti.');
        }

        $this->assertIpAllowed($request, $client);

        $allowedScopes = $client->allowed_scopes ?? [];
        if (! in_array($requiredScope, $allowedScopes, true)) {
            throw new AuthorizationException('Integration client is not allowed to use the required scope.');
        }

        if (! in_array($requiredScope, $scopes, true)) {
            throw new AuthorizationException('Integration token does not include the required scope.');
        }

        $this->jtiService->assertNotReplayed($client, $issuer, $jti, Carbon::createFromTimestamp($exp));

        $client->forceFill(['last_used_at' => now()])->save();

        return new IntegrationAuthContext(
            client: $client,
            claims: $claims,
            scopes: $scopes,
            issuer: $issuer,
            subject: is_string($subject) ? $subject : null,
            audience: $audience,
            jti: $jti,
        );
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization', ''));
        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    private function segment(string $token, int $index): string
    {
        $segments = explode('.', $token);

        if (! isset($segments[$index])) {
            throw new AuthenticationException('Malformed integration JWT.');
        }

        return $segments[$index];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSegment(string $segment, string $label): array
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);

        if (! is_array($decoded)) {
            throw new AuthenticationException("Invalid integration JWT {$label}.");
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new AuthenticationException('Invalid integration JWT encoding.');
        }

        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeScopes(mixed $scopeClaim): array
    {
        if (is_string($scopeClaim)) {
            return array_values(array_filter(preg_split('/\s+/', trim($scopeClaim)) ?: []));
        }

        if (is_array($scopeClaim)) {
            return array_values(array_filter(array_map(
                static fn ($value) => is_string($value) ? trim($value) : null,
                $scopeClaim
            )));
        }

        return [];
    }

    private function assertValidLifetime(int $iat, int $exp): void
    {
        $now = now()->timestamp;
        $maxTtl = max(60, (int) config('integrations.inbound.max_token_ttl_seconds', 600));

        if ($iat <= 0 || $exp <= 0 || $exp <= $iat) {
            throw new AuthenticationException('Invalid integration JWT lifetime.');
        }

        if ($iat > ($now + 60)) {
            throw new AuthenticationException('Integration JWT issued in the future.');
        }

        if ($exp < $now) {
            throw new AuthenticationException('Expired integration JWT.');
        }

        if (($exp - $iat) > $maxTtl) {
            throw new AuthenticationException('Integration JWT lifetime exceeds the allowed maximum.');
        }
    }

    private function assertIpAllowed(Request $request, IntegrationClient $client): void
    {
        $allowlist = $client->ip_allowlist ?? [];
        if ($allowlist === [] || $allowlist === null) {
            return;
        }

        $ip = $request->ip();
        if ($ip === null || ! in_array($ip, $allowlist, true)) {
            throw new AuthorizationException('Source IP is not allowed for this integration client.');
        }
    }
}
