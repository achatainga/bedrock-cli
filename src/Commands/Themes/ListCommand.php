<?php

namespace Roots\BedrockCli\Commands\Themes;

use Roots\BedrockCli\Commands\BaseListCommand;

class ListCommand extends BaseListCommand
{
    protected function getItemType(): string
    {
        return 'theme';
    }
}
