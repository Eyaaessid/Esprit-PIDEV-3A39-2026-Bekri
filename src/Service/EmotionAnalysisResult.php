<?php

namespace App\Service;

final class EmotionAnalysisResult
{
    public function __construct(
        public readonly string $emotion,
        public readonly string $riskLevel,
        public readonly bool $isSensitive,
        public readonly array $matchedSignals = [],
    ) {
    }
}
