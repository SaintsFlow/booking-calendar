<?php

namespace App\Infrastructure\CRM\Pipeline;

use App\Infrastructure\CRM\Bitrix24\DTO\PipelineContext;

/**
 * Интерфейс шага пайплайна
 */
interface PipelineStepInterface
{
    /**
     * Выполнить шаг пайплайна
     * 
     * @param PipelineContext $context
     * @return PipelineContext Обновлённый контекст
     */
    public function handle(PipelineContext $context): PipelineContext;

    /**
     * Название шага (для логирования)
     */
    public function getName(): string;

    /**
     * Должен ли шаг выполняться при текущих условиях
     */
    public function shouldExecute(PipelineContext $context): bool;
}
