<?php

namespace Roots\BedrockCli\ValueObjects;

class AcornValidation
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $message
    ) {}
}