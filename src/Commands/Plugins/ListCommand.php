<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Roots\BedrockCli\Commands\BaseListCommand;

class ListCommand extends BaseListCommand
{
    protected function getItemType(): string
    {
        return 'plugin';
    }
}
