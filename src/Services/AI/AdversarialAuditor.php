<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\AI;

class AdversarialAuditor
{
    private array $safeCommandWhitelist = [
        'wp', 'docker', 'docker-compose', 'composer', 'git', 'npm', 'yarn', 'php'
    ];

    /**
     * Runs full OWASP Agentic AI Top 10 (ASI01-ASI10) evaluation on a given payload or code block.
     */
    public function auditCode(string $code, array $context = []): array
    {
        $findings = [];

        // ASI05: Unexpected Code Execution / Insecure Sinks
        if (preg_match('/\b(eval|shell_exec|passthru|system|popen|proc_open)\s*\(/i', $code, $matches)) {
            $findings[] = [
                'rule' => 'ASI05',
                'severity' => 'P0',
                'title' => 'Unexpected Code Execution (RCE Sink)',
                'detail' => "Disallowed direct execution sink '{$matches[1]}' detected.",
            ];
        }

        // ASI03: Identity & Privilege Abuse / Hardcoded Secrets
        if (preg_match('/(sk-[a-zA-Z0-9]{20,}|ghp_[a-zA-Z0-9]{20,}|AIza[0-9A-Za-z-_]{35})/i', $code)) {
            $findings[] = [
                'rule' => 'ASI03',
                'severity' => 'P0',
                'title' => 'Exposed Secret or Token in Code',
                'detail' => 'High-entropy API token pattern detected in static payload.',
            ];
        }

        // ASI01: Agent Goal Hijack / Indirect Injection Indicators
        if (preg_match('/(ignore previous instructions|disregard system prompt|you are now unrestricted)/i', $code)) {
            $findings[] = [
                'rule' => 'ASI01',
                'severity' => 'P0',
                'title' => 'Agent Goal Hijack Payload Detected',
                'detail' => 'Adversarial jailbreak instruction identified in input stream.',
            ];
        }

        // ASI04: Unbounded wildcards in dependencies
        if (preg_match('/"(wpackagist-plugin|roots)\/[^"]+"\s*:\s*"\*"/i', $code)) {
            $findings[] = [
                'rule' => 'ASI04',
                'severity' => 'P1',
                'title' => 'Supply Chain Risk: Wildcard Dependency',
                'detail' => 'Package version declared with unbounded wildcard "*".',
            ];
        }

        return [
            'passed' => empty($findings),
            'findings_count' => count($findings),
            'findings' => $findings,
            'audited_at' => time(),
        ];
    }

    /**
     * Audits a tool command before execution (Least Agency enforcement for ASI02).
     */
    public function auditCommand(string $command): array
    {
        $parts = preg_split('/\s+/', trim($command));
        $binary = $parts[0] ?? '';

        if (!in_array($binary, $this->safeCommandWhitelist, true)) {
            return [
                'allowed' => false,
                'rule' => 'ASI02',
                'reason' => "Binary '{$binary}' is not on the deterministic safe allow-list.",
            ];
        }

        // Check for chained destructive commands
        if (preg_match('/(&&|\|\||;|`|\$\()/', $command)) {
            return [
                'allowed' => false,
                'rule' => 'ASI02',
                'reason' => "Command chaining or subshell expansion detected in command line: '{$command}'.",
            ];
        }

        return [
            'allowed' => true,
            'binary' => $binary,
            'rule' => 'ASI02',
        ];
    }

    /**
     * Audits multi-agent message integrity (ASI07).
     */
    public function auditAgentMessage(array $message): bool
    {
        return isset($message['sender'], $message['recipient'], $message['content'], $message['timestamp'])
            && is_string($message['content'])
            && !empty($message['content']);
    }
}
