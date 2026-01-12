<?php

namespace Roots\BedrockCli\Commands\Themes;

use Roots\BedrockCli\Commands\BaseMenuCommand;

class MenuCommand extends BaseMenuCommand
{
    protected function getItemType(): string
    {
        return 'theme';
    }

    protected function getPluralType(): string
    {
        return 'themes';
    }

    protected function getMenuTitle(): string
    {
        return '🎨 THEMES - Gestión';
    }
}
