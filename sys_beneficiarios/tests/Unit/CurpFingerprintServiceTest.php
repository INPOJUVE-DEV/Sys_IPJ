<?php

namespace Tests\Unit;

use App\Services\Integrations\ApiTj\CurpFingerprintService;
use Tests\TestCase;

class CurpFingerprintServiceTest extends TestCase
{
    public function test_it_normalizes_hashes_and_masks_curp(): void
    {
        config([
            'integrations.outbound.hash_secret' => 'test-curp-secret',
        ]);

        $service = app(CurpFingerprintService::class);

        $this->assertSame('MELR000101HSPABC06', $service->normalize(' melr000101hspabc06 '));
        $this->assertSame(
            hash_hmac('sha256', 'MELR000101HSPABC06', 'test-curp-secret'),
            $service->hash(' melr000101hspabc06 ')
        );
        $this->assertSame('MELR************06', $service->mask('melr000101hspabc06'));
    }
}
