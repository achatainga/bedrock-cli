<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Roots\BedrockCli\Commands\BaseCompressCommand;

class CompressCommand extends BaseCompressCommand
{
    protected function getItemType(): string
    {
        return 'plugin';
    }
}
