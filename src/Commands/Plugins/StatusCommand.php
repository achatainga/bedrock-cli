<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Roots\BedrockCli\Commands\BaseStatusCommand;

class StatusCommand extends BaseStatusCommand
{
    protected function getItemType(): string
    {
        return 'plugin';
    }
}
