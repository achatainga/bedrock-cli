<?php

namespace Achatainga\BedrockCli\ValueObjects;

class DockerValidation
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $message
    ) {}
}