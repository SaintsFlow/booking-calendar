<?php

namespace App\Infrastructure\CRM\Pipeline;

use App\Infrastructure\CRM\Bitrix24\DTO\PipelineContext;
use Illuminate\Support\Facades\Log;

/**
 * Главный класс Pipeline для обработки CRM операций
 */
class CrmPipeline
{
    /**
     * @var PipelineStepInterface[]
     */
    private array $steps = [];

    public function __construct(array $steps = [])
    {
        $this->steps = $steps;
    }

    /**
     * Добавить шаг в пайплайн
     */
    public function addStep(PipelineStepInterface $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    /**
     * Выполнить весь пайплайн
     */
    public function execute(PipelineContext $context): PipelineContext
    {
        Log::info('🚀 Starting CRM Pipeline', [
            'tenant_id' => $context->tenantId,
            'booking_id' => $context->bookingId,
            'steps_count' => count($this->steps),
        ]);

        $currentContext = $context;

        foreach ($this->steps as $index => $step) {
            $stepName = $step->getName();
            $stepNumber = $index + 1;

            try {
                // Проверяем, нужно ли выполнять шаг
                if (!$step->shouldExecute($currentContext)) {
                    Log::info("⏭️  Step {$stepNumber}/" . count($this->steps) . ": {$stepName} - SKIPPED", [
                        'reason' => 'shouldExecute returned false',
                    ]);
                    continue;
                }

                Log::info("▶️  Step {$stepNumber}/" . count($this->steps) . ": {$stepName} - STARTED");

                $startTime = microtime(true);
                $currentContext = $step->handle($currentContext);
                $duration = round((microtime(true) - $startTime) * 1000, 2);

                Log::info("✅ Step {$stepNumber}/" . count($this->steps) . ": {$stepName} - COMPLETED", [
                    'duration_ms' => $duration,
                    'contact_ids' => $currentContext->contactIds ?? [],
                    'deal_ids' => $currentContext->dealIds ?? [],
                ]);
            } catch (\Throwable $e) {
                Log::error("❌ Step {$stepNumber}/" . count($this->steps) . ": {$stepName} - FAILED", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Можно добавить стратегию обработки ошибок:
                // - продолжить выполнение
                // - прервать пайплайн
                // - откатить изменения
                throw $e;
            }
        }

        Log::info('🎉 CRM Pipeline completed successfully', [
            'tenant_id' => $context->tenantId,
            'created_contact_id' => $currentContext->createdContactId,
            'created_deal_id' => $currentContext->createdDealId,
            'total_contacts' => count($currentContext->contactIds),
            'total_deals' => count($currentContext->dealIds),
        ]);

        return $currentContext;
    }

    /**
     * Получить список всех шагов
     */
    public function getSteps(): array
    {
        return $this->steps;
    }
}
