<?php

namespace Achatainga\BedrockCli\ValueObjects;

class WordPressValidation
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $message
    ) {}
}