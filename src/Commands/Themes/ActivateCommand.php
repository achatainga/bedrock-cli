<?php

namespace Roots\BedrockCli\Commands\Themes;

use Roots\BedrockCli\Commands\BaseActivateCommand;

class ActivateCommand extends BaseActivateCommand
{
    protected function getItemType(): string
    {
        return 'theme';
    }

    protected function getStepId(): int
    {
        return 4;
    }
}
