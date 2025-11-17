<?php

namespace Roots\BedrockCli\ValueObjects;

class AcornValidation
{
    public function __construct(
        public readonly bool $isPackageInstalled,
        public readonly bool $isStorageInitialized,
        public readonly bool $areConfigsPublished,
        public readonly bool $isValid, // Overall validity (all three must be true)
        public readonly string $message // Summary message
    ) {}
}