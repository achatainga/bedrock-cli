<?php

namespace Achatainga\BedrockCli\ValueObjects;

class AcornValidation
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $message
    ) {}
}