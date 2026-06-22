<?php

namespace App\Services\Integrations\ApiTj;

use App\Jobs\Integrations\ApiTj\RunCardholderSyncJob;
use App\Models\Beneficiario;
use App\Models\Integrations\IntegrationSyncItem;
use App\Models\Integrations\IntegrationSyncRun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CardholderSyncService
{
    private const TARGET_SYSTEM = 'api_tj';

    private const OPERATION = 'cardholders.sync';

    public function __construct(
        private readonly CardholderSyncSelector $selector,
        private readonly CardholderPayloadFactory $payloadFactory,
        private readonly ApiTjClient $client,
    ) {
    }

    public function queue(User $actor, array $options = []): IntegrationSyncRun
    {
        return $this->queueRun(
            $this->selector->queryEligible()->get(),
            $actor,
        );
    }

    public function queueBeneficiario(Beneficiario $beneficiario, User $actor): IntegrationSyncRun
    {
        $target = Beneficiario::query()
            ->with([
                'tarjeta' => fn ($query) => $query->select(['id', 'folio', 'estatus', 'beneficiario_id']),
            ])
            ->findOrFail($beneficiario->id);

        return $this->queueRun(collect([$target]), $actor);
    }

    private function queueRun(Collection $beneficiarios, User $actor): IntegrationSyncRun
    {
        $run = DB::transaction(function () use ($beneficiarios, $actor) {
            $run = IntegrationSyncRun::query()->create([
                'id' => (string) Str::uuid(),
                'target_system' => self::TARGET_SYSTEM,
                'operation' => self::OPERATION,
                'status' => IntegrationSyncRun::STATUS_PENDING,
                'requested_by' => $actor->uuid,
            ]);

            $syncMoment = $run->created_at ?? now();
            $pendingCount = 0;
            $skippedCount = 0;
            $totalItems = $beneficiarios->count();

            foreach ($beneficiarios as $beneficiario) {
                $itemStatus = $this->createSyncItem($run, $beneficiario, $syncMoment);

                if ($itemStatus === IntegrationSyncItem::STATUS_PENDING) {
                    $pendingCount++;
                    continue;
                }

                if ($itemStatus === IntegrationSyncItem::STATUS_SKIPPED) {
                    $skippedCount++;
                }
            }

            $run->forceFill([
                'total_items' => $totalItems,
                'skipped_count' => $skippedCount,
                'status' => $pendingCount > 0
                    ? IntegrationSyncRun::STATUS_QUEUED
                    : IntegrationSyncRun::STATUS_SUCCESS,
                'finished_at' => $pendingCount > 0 ? null : now(),
            ])->save();

            return $run;
        });

        if ($run->status === IntegrationSyncRun::STATUS_QUEUED) {
            RunCardholderSyncJob::dispatch($run->id);
        }

        return $run->fresh(['items']);
    }

    private function createSyncItem(IntegrationSyncRun $run, Beneficiario $beneficiario, $syncMoment): string
    {
        try {
            $payload = $this->payloadFactory->makeItem($beneficiario, $syncMoment);

            $run->items()->create([
                'id' => (string) Str::uuid(),
                'beneficiario_id' => $beneficiario->id,
                'payload_hash' => $this->hashPayload($payload),
                'status' => IntegrationSyncItem::STATUS_PENDING,
            ]);

            return IntegrationSyncItem::STATUS_PENDING;
        } catch (SkipSyncItemException $exception) {
            $run->items()->create([
                'id' => (string) Str::uuid(),
                'beneficiario_id' => $beneficiario->id,
                'payload_hash' => $this->hashSkip($beneficiario, $exception->getMessage()),
                'status' => IntegrationSyncItem::STATUS_SKIPPED,
                'error_message' => $exception->getMessage(),
            ]);

            return IntegrationSyncItem::STATUS_SKIPPED;
        }
    }

    public function run(IntegrationSyncRun $run): void
    {
        $run = $run->fresh();
        if (! $run) {
            return;
        }

        if (in_array($run->status, [
            IntegrationSyncRun::STATUS_SUCCESS,
            IntegrationSyncRun::STATUS_PARTIAL,
            IntegrationSyncRun::STATUS_FAILED,
            IntegrationSyncRun::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $run->forceFill([
            'status' => IntegrationSyncRun::STATUS_RUNNING,
            'started_at' => $run->started_at ?? now(),
            'finished_at' => null,
            'error_message' => null,
        ])->save();

        $successCount = (int) $run->success_count;
        $failedCount = (int) $run->failed_count;
        $skippedCount = (int) $run->skipped_count;
        $syncMoment = $run->created_at ?? $run->started_at ?? now();
        $pendingItems = $run->items()
            ->where('status', IntegrationSyncItem::STATUS_PENDING)
            ->with(['beneficiario.tarjeta'])
            ->orderBy('created_at')
            ->get();

        if ($pendingItems->isEmpty()) {
            $this->finalizeRun($run, $successCount, $failedCount, $skippedCount);

            return;
        }

        $batchSize = max(1, (int) config('integrations.outbound.batch_size', 100));

        try {
            foreach ($pendingItems->chunk($batchSize) as $batch) {
                $preparedBatch = $this->prepareBatch($batch, $syncMoment);

                $successCount += $preparedBatch['success'];
                $failedCount += $preparedBatch['failed'];
                $skippedCount += $preparedBatch['skipped'];

                if ($preparedBatch['items'] === []) {
                    continue;
                }

                $response = $this->client->syncCardholders(
                    $this->makeSyncId($run),
                    $preparedBatch['items'],
                );

                $applied = $this->applyResponse($preparedBatch['map'], $response);
                $successCount += $applied['success'];
                $failedCount += $applied['failed'];
                $skippedCount += $applied['skipped'];

                if ($applied['abort']) {
                    $this->failRemainingPendingItems($run, $response->message() ?? 'Outbound sync aborted after API_TJ failure.');
                    $failedCount = $run->items()
                        ->whereIn('status', [IntegrationSyncItem::STATUS_ERROR, IntegrationSyncItem::STATUS_REJECTED])
                        ->count();

                    $this->finalizeRun(
                        $run,
                        $successCount,
                        $failedCount,
                        $skippedCount,
                        $response->message() ?? 'Outbound sync failed.',
                    );

                    return;
                }
            }
        } catch (Throwable $exception) {
            $this->failRemainingPendingItems($run, $exception->getMessage());
            $failedCount = $run->items()
                ->whereIn('status', [IntegrationSyncItem::STATUS_ERROR, IntegrationSyncItem::STATUS_REJECTED])
                ->count();

            $this->finalizeRun($run, $successCount, $failedCount, $skippedCount, $exception->getMessage());

            return;
        }

        $this->finalizeRun($run, $successCount, $failedCount, $skippedCount);
    }

    /**
     * @param  Collection<int, IntegrationSyncItem>  $batch
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     map: array<int, IntegrationSyncItem>,
     *     success: int,
     *     failed: int,
     *     skipped: int
     * }
     */
    private function prepareBatch(Collection $batch, $syncMoment): array
    {
        $items = [];
        $map = [];
        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($batch as $syncItem) {
            $beneficiario = $syncItem->beneficiario;

            if (! $beneficiario) {
                $this->markItem(
                    $syncItem,
                    IntegrationSyncItem::STATUS_ERROR,
                    null,
                    null,
                    'Beneficiary no longer exists for this sync item.',
                );

                $failed++;

                continue;
            }

            try {
                $payload = $this->payloadFactory->makeItem($beneficiario, $syncMoment);
                $syncItem->forceFill([
                    'payload_hash' => $this->hashPayload($payload),
                ])->save();

                $map[count($items)] = $syncItem->fresh();
                $items[] = $payload;
            } catch (SkipSyncItemException $exception) {
                $this->markItem(
                    $syncItem,
                    IntegrationSyncItem::STATUS_SKIPPED,
                    null,
                    null,
                    $exception->getMessage(),
                );

                $skipped++;
            } catch (Throwable $exception) {
                $this->markItem(
                    $syncItem,
                    IntegrationSyncItem::STATUS_ERROR,
                    null,
                    null,
                    $exception->getMessage(),
                );

                $failed++;
            }
        }

        return compact('items', 'map', 'success', 'failed', 'skipped');
    }

    /**
     * @param  array<int, IntegrationSyncItem>  $indexMap
     * @return array{success: int, failed: int, skipped: int, abort: bool}
     */
    private function applyResponse(array $indexMap, ApiTjSyncResponse $response): array
    {
        $success = 0;
        $failed = 0;
        $skipped = 0;
        $abort = false;

        if ($response->statusCode >= 400) {
            foreach ($indexMap as $syncItem) {
                $this->markItem(
                    $syncItem,
                    IntegrationSyncItem::STATUS_ERROR,
                    $response->statusCode,
                    $response->body,
                    $response->message() ?? 'API_TJ rejected the outbound sync request.',
                );
            }

            return [
                'success' => 0,
                'failed' => count($indexMap),
                'skipped' => 0,
                'abort' => true,
            ];
        }

        if ($response->results() === []) {
            $status = $response->accepted()
                ? IntegrationSyncItem::STATUS_ACCEPTED
                : IntegrationSyncItem::STATUS_ERROR;
            $error = $response->accepted()
                ? null
                : ($response->message() ?? 'API_TJ did not accept the outbound sync request.');

            foreach ($indexMap as $syncItem) {
                $this->markItem($syncItem, $status, $response->statusCode, $response->body, $error);
            }

            return [
                'success' => $response->accepted() ? count($indexMap) : 0,
                'failed' => $response->accepted() ? 0 : count($indexMap),
                'skipped' => 0,
                'abort' => ! $response->accepted(),
            ];
        }

        foreach ($indexMap as $index => $syncItem) {
            $result = $response->resultForIndex($index);
            $resultStatus = strtolower(trim((string) ($result['status'] ?? '')));
            $message = $this->resolveResultMessage($result, $response);

            if ($this->isRejectedResult($resultStatus)) {
                $this->markItem(
                    $syncItem,
                    IntegrationSyncItem::STATUS_REJECTED,
                    $response->statusCode,
                    $result ?? $response->body,
                    $message,
                );

                $failed++;

                continue;
            }

            if ($this->isSkippedResult($resultStatus)) {
                $this->markItem(
                    $syncItem,
                    IntegrationSyncItem::STATUS_SKIPPED,
                    $response->statusCode,
                    $result ?? $response->body,
                    $message,
                );

                $skipped++;

                continue;
            }

            if ($this->isErrorResult($resultStatus)) {
                $this->markItem(
                    $syncItem,
                    IntegrationSyncItem::STATUS_ERROR,
                    $response->statusCode,
                    $result ?? $response->body,
                    $message ?? 'API_TJ returned an item error.',
                );

                $failed++;

                continue;
            }

            if ($result === null && ! $response->accepted()) {
                $this->markItem(
                    $syncItem,
                    IntegrationSyncItem::STATUS_ERROR,
                    $response->statusCode,
                    $response->body,
                    $response->message() ?? 'API_TJ returned an invalid sync response.',
                );

                $failed++;
                $abort = true;

                continue;
            }

            $this->markItem(
                $syncItem,
                IntegrationSyncItem::STATUS_ACCEPTED,
                $response->statusCode,
                $result ?? $response->body,
                null,
            );

            $success++;
        }

        return compact('success', 'failed', 'skipped', 'abort');
    }

    private function finalizeRun(
        IntegrationSyncRun $run,
        int $successCount,
        int $failedCount,
        int $skippedCount,
        ?string $errorMessage = null,
    ): void {
        $status = IntegrationSyncRun::STATUS_SUCCESS;

        if ($failedCount > 0 && $successCount > 0) {
            $status = IntegrationSyncRun::STATUS_PARTIAL;
        } elseif ($failedCount > 0) {
            $status = IntegrationSyncRun::STATUS_FAILED;
        } elseif ($skippedCount > 0) {
            $status = IntegrationSyncRun::STATUS_PARTIAL;
        }

        $run->forceFill([
            'status' => $status,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'skipped_count' => $skippedCount,
            'finished_at' => now(),
            'error_message' => $errorMessage,
        ])->save();
    }

    private function failRemainingPendingItems(IntegrationSyncRun $run, string $message): void
    {
        $run->items()
            ->where('status', IntegrationSyncItem::STATUS_PENDING)
            ->get()
            ->each(fn (IntegrationSyncItem $item) => $this->markItem(
                $item,
                IntegrationSyncItem::STATUS_ERROR,
                null,
                null,
                $message,
            ));
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function markItem(
        IntegrationSyncItem $item,
        string $status,
        ?int $responseCode,
        ?array $body,
        ?string $errorMessage,
    ): void {
        $item->forceFill([
            'status' => $status,
            'response_code' => $responseCode,
            'response_body' => $body,
            'error_message' => $errorMessage,
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function resolveResultMessage(?array $result, ApiTjSyncResponse $response): ?string
    {
        if ($result) {
            foreach (['message', 'reason', 'error', 'detail', 'action'] as $field) {
                $value = $result[$field] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return $response->message();
    }

    private function isRejectedResult(string $status): bool
    {
        return in_array($status, [
            'conflict',
            'rejected',
            'invalid',
            'duplicate',
            'failed',
        ], true);
    }

    private function isSkippedResult(string $status): bool
    {
        return $status === 'skipped';
    }

    private function isErrorResult(string $status): bool
    {
        return $status === 'error';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function hashSkip(Beneficiario $beneficiario, string $message): string
    {
        return hash('sha256', $beneficiario->id.'|'.$message);
    }

    private function makeSyncId(IntegrationSyncRun $run): string
    {
        $timestamp = ($run->created_at ?? now())->format('YmdHis');
        $suffix = strtoupper(substr(str_replace('-', '', $run->id), 0, 8));

        return "SYS-IPJ-{$timestamp}-{$suffix}";
    }
}
