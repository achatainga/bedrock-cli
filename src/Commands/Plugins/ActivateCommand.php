<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Roots\BedrockCli\Commands\BaseActivateCommand;

class ActivateCommand extends BaseActivateCommand
{
    protected function getItemType(): string
    {
        return 'plugin';
    }

    protected function getStepId(): int
    {
        return 3;
    }
}
