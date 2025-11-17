<?php

namespace Roots\BedrockCli\ValueObjects;

class DatabaseValidation
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $message
    ) {}
}