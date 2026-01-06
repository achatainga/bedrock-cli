<?php
// src/Core/ServiceBridge.php
namespace BedrockCli\Plugin\Core;

use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\ProjectValidationService;
use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\ProjectDiagnosticService;

class ServiceBridge
{
    private static $instance = null;
    private $services = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $serviceClass)
    {
        if (isset($this->services[$serviceClass])) {
            return $this->services[$serviceClass];
        }

        // Inyección de dependencias manual (Factory)
        $docker = new DockerService();
        $context = new ContextDetector($docker);
        $wpCli = new WpCliService($docker, $context);
        
        // Mapeo de servicios
        switch ($serviceClass) {
            case 'DockerService':
                $instance = $docker;
                break;
            case 'WpCliService':
                $instance = $wpCli;
                break;
            case 'ProjectValidationService':
                $instance = new ProjectValidationService($context);
                break;
            case 'ProjectDiagnosticService':
                $instance = new ProjectDiagnosticService();
                break;
            default:
                throw new \Exception("Servicio {$serviceClass} no definido en Bridge.");
        }

        $this->services[$serviceClass] = $instance;
        return $instance;
    }
}
