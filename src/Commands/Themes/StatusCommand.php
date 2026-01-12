<?php

namespace Roots\BedrockCli\Commands\Themes;

use Roots\BedrockCli\Commands\BaseStatusCommand;

class StatusCommand extends BaseStatusCommand
{
    protected function getItemType(): string
    {
        return 'theme';
    }
}
