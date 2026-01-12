<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Roots\BedrockCli\Commands\BaseMenuCommand;

class MenuCommand extends BaseMenuCommand
{
    protected function getItemType(): string
    {
        return 'plugin';
    }

    protected function getPluralType(): string
    {
        return 'plugins';
    }

    protected function getMenuTitle(): string
    {
        return '🔌 PLUGINS - Gestión';
    }
}
