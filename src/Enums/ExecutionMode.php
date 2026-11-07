<?php

namespace Roots\BedrockCli\Enums;

enum ExecutionMode: string
{
    case DOCKER = 'docker';   // Todo dentro de contenedores (comportamiento actual)
    case HYBRID = 'hybrid';   // PHP local + DB/Redis en Docker
    case NATIVE = 'native';   // Todo local (PHP, MySQL, Redis nativos)
}
