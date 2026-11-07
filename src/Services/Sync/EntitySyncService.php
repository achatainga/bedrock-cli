<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\Sync;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class EntitySyncService
{
    private EntityComparatorService $comparator;

    public function __construct(?EntityComparatorService $comparator = null)
    {
        $this->comparator = $comparator ?? new EntityComparatorService();
    }

    public function getComparator(): EntityComparatorService
    {
        return $this->comparator;
    }

    /**
     * Resuelve el comando de ejecución según el alias del entorno.
     */
    public function executeInEnvironment(string $envAlias, string $phpCode): array
    {
        $process = match ($envAlias) {
            '9010', 'clone9010', 'staging:9010' => new Process([
                'ssh', 'dt24-godaddy',
                "docker exec -i dt24-clone9010_web wp eval-file - --allow-root"
            ]),
            'staging', 'bedrock', '443', 'staging:443' => new Process([
                'ssh', 'dt24-godaddy',
                "docker exec -i dt24-bedrock_web wp eval-file - --allow-root"
            ]),
            default => new Process(
                array_merge($this->getComposeCommand(), ['exec', '-T', 'web', 'wp', 'eval-file', '-', '--allow-root'])
            ),
        };

        $process->setInput("<?php\n" . $phpCode);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            return [
                'success' => false,
                'error'   => trim($process->getErrorOutput() ?: $process->getOutput()),
                'data'    => null,
            ];
        }

        $rawOutput = trim($process->getOutput());
        // Filtrar notices o banners previos al JSON
        $jsonStart = strpos($rawOutput, '{');
        $jsonArrayStart = strpos($rawOutput, '[');
        
        $startPos = false;
        if ($jsonStart !== false && $jsonArrayStart !== false) {
            $startPos = min($jsonStart, $jsonArrayStart);
        } elseif ($jsonStart !== false) {
            $startPos = $jsonStart;
        } elseif ($jsonArrayStart !== false) {
            $startPos = $jsonArrayStart;
        }

        if ($startPos !== false) {
            $cleanJson = substr($rawOutput, $startPos);
            $decoded = json_decode($cleanJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'success' => true,
                    'error'   => null,
                    'data'    => $decoded,
                ];
            }
        }

        return [
            'success' => false,
            'error'   => 'Respuesta JSON inválida: ' . substr($rawOutput, 0, 300),
            'data'    => null,
        ];
    }

    /**
     * Extrae el catálogo completo de un entorno dado.
     */
    public function fetchCatalog(string $envAlias): array
    {
        $script = $this->comparator->getCatalogExtractorScript();
        $res = $this->executeInEnvironment($envAlias, $script);
        if (!$res['success'] || !is_array($res['data'])) {
            throw new \RuntimeException("Error al extraer catálogo de '{$envAlias}': " . ($res['error'] ?? 'desconocido'));
        }
        return $res['data'];
    }

    /**
     * Directorio base de sincronización en el proyecto.
     */
    public function getSyncDir(string $projectRoot): string
    {
        $dir = rtrim($projectRoot, '/\\') . '/config/sync';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Obtiene las entidades preparadas (staged).
     */
    public function getStaged(string $projectRoot): array
    {
        $file = $this->getSyncDir($projectRoot) . '/.staged.json';
        if (!file_exists($file)) {
            return [];
        }
        $content = file_get_contents($file);
        return $content ? (json_decode($content, true) ?: []) : [];
    }

    /**
     * Agrega entidades al área de preparación (stage).
     */
    public function stageEntities(string $projectRoot, array $entities): int
    {
        $current = $this->getStaged($projectRoot);
        foreach ($entities as $k => $e) {
            $current[$k] = $e;
        }
        $file = $this->getSyncDir($projectRoot) . '/.staged.json';
        file_put_contents($file, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return count($entities);
    }

    /**
     * Limpia el área de preparación (unstage).
     */
    public function unstage(string $projectRoot, ?string $key = null): void
    {
        $file = $this->getSyncDir($projectRoot) . '/.staged.json';
        if (!file_exists($file)) {
            return;
        }
        if ($key === null) {
            @unlink($file);
            return;
        }
        $current = $this->getStaged($projectRoot);
        unset($current[$key]);
        file_put_contents($file, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Empaqueta lo staged en un changeset inmutable (commit).
     */
    public function commitChangeset(string $projectRoot, string $message, string $author = 'developer'): array
    {
        $staged = $this->getStaged($projectRoot);
        if (empty($staged)) {
            throw new \RuntimeException('No hay entidades preparadas (staged) para commitear. Usa sync:stage primero.');
        }

        $changesetsDir = $this->getSyncDir($projectRoot) . '/changesets';
        if (!is_dir($changesetsDir)) {
            mkdir($changesetsDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $id = "cs_{$timestamp}";
        $filename = "{$changesetsDir}/{$id}.json";

        $manifest = [
            'id'        => $id,
            'timestamp' => date('c'),
            'author'    => $author,
            'message'   => $message,
            'count'     => count($staged),
            'entities'  => $staged,
        ];

        file_put_contents($filename, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->unstage($projectRoot);

        return $manifest;
    }

    /**
     * Lista los changesets disponibles.
     */
    public function listChangesets(string $projectRoot): array
    {
        $dir = $this->getSyncDir($projectRoot) . '/changesets';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob("{$dir}/cs_*.json") ?: [];
        rsort($files);

        $list = [];
        foreach ($files as $f) {
            $data = json_decode((string) file_get_contents($f), true);
            if ($data) {
                $list[] = [
                    'id'        => $data['id'] ?? basename($f, '.json'),
                    'timestamp' => $data['timestamp'] ?? '',
                    'message'   => $data['message'] ?? '',
                    'count'     => $data['count'] ?? count($data['entities'] ?? []),
                    'file'      => $f,
                ];
            }
        }
        return $list;
    }

    /**
     * Carga un changeset específico.
     */
    public function loadChangeset(string $projectRoot, string $changesetId): ?array
    {
        $dir = $this->getSyncDir($projectRoot) . '/changesets';
        $targetFile = str_ends_with($changesetId, '.json') ? $changesetId : "{$dir}/{$changesetId}.json";
        if (!file_exists($targetFile)) {
            return null;
        }
        return json_decode((string) file_get_contents($targetFile), true);
    }

    /**
     * Genera el script PHP para aplicar (inyectar) entidades en el destino de forma idempotente.
     */
    public function generateInjectionScript(array $entities): string
    {
        $payloadB64 = base64_encode(json_encode($entities, JSON_UNESCAPED_UNICODE));

        return <<<PHP
\$payload = json_decode(base64_decode('{$payloadB64}'), true);
if (!is_array(\$payload)) {
    echo json_encode(['success' => false, 'error' => 'Payload JSON corrupto']);
    exit(0);
}

if (!function_exists('wc_get_product')) {
    echo json_encode(['success' => false, 'error' => 'WooCommerce no activo en destino']);
    exit(0);
}

\$applied = ['created' => [], 'updated' => [], 'errors' => []];

foreach (\$payload as \$sku => \$data) {
    try {
        \$existingId = wc_get_product_id_by_sku(\$sku);
        if (!\$existingId && !empty(\$data['slug'])) {
            \$page = get_page_by_path(\$data['slug'], OBJECT, 'product');
            if (\$page) {
                \$existingId = \$page->ID;
            }
        }

        if (\$existingId > 0) {
            // Actualizar en sitio preservando ID de destino
            \$product = wc_get_product(\$existingId);
            if (!\$product) {
                \$product = new \WC_Product_Simple(\$existingId);
            }
            \$isNew = false;
        } else {
            // Crear nuevo
            \$product = new \WC_Product_Simple();
            \$isNew = true;
        }

        if (isset(\$data['name']) && \$data['name'] !== '') \$product->set_name((string) \$data['name']);
        if (isset(\$data['slug']) && \$data['slug'] !== '') \$product->set_slug((string) \$data['slug']);
        if (isset(\$data['sku']) && \$data['sku'] !== '') \$product->set_sku((string) \$data['sku']);
        if (isset(\$data['status'])) \$product->set_status((string) \$data['status']);
        if (array_key_exists('regular_price', \$data)) \$product->set_regular_price(\$data['regular_price'] !== null ? (string) \$data['regular_price'] : '');
        if (array_key_exists('sale_price', \$data)) \$product->set_sale_price(\$data['sale_price'] !== null ? (string) \$data['sale_price'] : '');
        if (array_key_exists('price', \$data)) \$product->set_price(\$data['price'] !== null ? (string) \$data['price'] : '');
        if (isset(\$data['stock_status'])) \$product->set_stock_status((string) \$data['stock_status']);
        if (array_key_exists('manage_stock', \$data)) \$product->set_manage_stock((bool) \$data['manage_stock']);
        if (array_key_exists('stock_quantity', \$data)) \$product->set_stock_quantity(\$data['stock_quantity'] !== null ? (int) \$data['stock_quantity'] : null);
        if (array_key_exists('weight', \$data)) \$product->set_weight(\$data['weight'] !== null ? (string) \$data['weight'] : '');
        if (array_key_exists('short_description', \$data)) \$product->set_short_description((string) \$data['short_description']);
        if (array_key_exists('description', \$data)) \$product->set_description((string) \$data['description']);

        // Guardar producto
        \$savedId = \$product->save();

        // Categorías
        if (!empty(\$data['categories']) && is_array(\$data['categories'])) {
            \$catIds = [];
            foreach (\$data['categories'] as \$c) {
                \$slug = is_array(\$c) ? (\$c['slug'] ?? '') : (string) \$c;
                if (empty(\$slug)) {
                    continue;
                }
                \$name = is_array(\$c) ? (\$c['name'] ?? \$slug) : \$slug;
                \$term = get_term_by('slug', \$slug, 'product_cat');
                if (!\$term) {
                    \$newTerm = wp_insert_term(\$name, 'product_cat', ['slug' => \$slug]);
                    if (!is_wp_error(\$newTerm) && isset(\$newTerm['term_id'])) {
                        \$catIds[] = (int) \$newTerm['term_id'];
                    }
                } else {
                    \$catIds[] = (int) \$term->term_id;
                }
            }
            if (!empty(\$catIds)) {
                wp_set_object_terms(\$savedId, \$catIds, 'product_cat');
            }
        }

        // Autor / Vendedor
        if (!empty(\$data['meta']['_vendor_id'])) {
            wp_update_post([
                'ID' => \$savedId,
                'post_author' => (int) \$data['meta']['_vendor_id'],
            ]);
        }

        if (\$isNew) {
            \$applied['created'][] = ['sku' => \$sku, 'id' => \$savedId, 'name' => \$data['name']];
        } else {
            \$applied['updated'][] = ['sku' => \$sku, 'id' => \$savedId, 'name' => \$data['name']];
        }
    } catch (\\Throwable \$e) {
        \$applied['errors'][] = ['sku' => \$sku, 'error' => \$e->getMessage()];
    }
}

if (function_exists('wc_delete_product_transients')) {
    wc_delete_product_transients();
}
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

echo json_encode(['success' => true, 'applied' => \$applied], JSON_UNESCAPED_UNICODE);
PHP;
    }

    /**
     * Genera el script PHP para revertir un changeset: elimina entidades creadas y restaura modificadas.
     */
    public function generateRollbackScript(array $savedState, array $createdEntities): string
    {
        $payloadB64 = base64_encode(json_encode([
            'saved'   => $savedState,
            'created' => $createdEntities,
        ], JSON_UNESCAPED_UNICODE));

        return <<<PHP
\$payload = json_decode(base64_decode('{$payloadB64}'), true);
if (!is_array(\$payload)) {
    echo json_encode(['success' => false, 'error' => 'Payload de rollback corrupto']);
    exit(0);
}

\$applied = ['deleted' => [], 'restored' => [], 'errors' => []];

// 1. Eliminar entidades que fueron creadas por el push
if (!empty(\$payload['created']) && is_array(\$payload['created'])) {
    foreach (\$payload['created'] as \$c) {
        \$sku = is_array(\$c) ? (\$c['sku'] ?? null) : null;
        \$id = is_array(\$c) ? (\$c['id'] ?? null) : null;
        try {
            if (!\$id && \$sku && function_exists('wc_get_product_id_by_sku')) {
                \$id = wc_get_product_id_by_sku(\$sku);
            }
            if (\$id > 0) {
                wp_delete_post((int) \$id, true);
                \$applied['deleted'][] = ['sku' => \$sku, 'id' => \$id];
            }
        } catch (\\Throwable \$e) {
            \$applied['errors'][] = ['action' => 'delete', 'sku' => \$sku, 'error' => \$e->getMessage()];
        }
    }
}

// 2. Restaurar estado de entidades que fueron modificadas
if (!empty(\$payload['saved']) && is_array(\$payload['saved'])) {
    foreach (\$payload['saved'] as \$sku => \$data) {
        try {
            \$existingId = function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku(\$sku) : 0;
            if (!\$existingId && !empty(\$data['slug'])) {
                \$page = get_page_by_path(\$data['slug'], OBJECT, 'product');
                if (\$page) {
                    \$existingId = \$page->ID;
                }
            }

            if (\$existingId > 0 && function_exists('wc_get_product')) {
                \$product = wc_get_product(\$existingId);
                if (!\$product) {
                    \$product = new \\WC_Product_Simple(\$existingId);
                }
                if (isset(\$data['name']) && \$data['name'] !== '') \$product->set_name((string) \$data['name']);
                if (isset(\$data['slug']) && \$data['slug'] !== '') \$product->set_slug((string) \$data['slug']);
                if (isset(\$data['sku']) && \$data['sku'] !== '') \$product->set_sku((string) \$data['sku']);
                if (isset(\$data['status'])) \$product->set_status((string) \$data['status']);
                if (array_key_exists('regular_price', \$data)) \$product->set_regular_price(\$data['regular_price'] !== null ? (string) \$data['regular_price'] : '');
                if (array_key_exists('sale_price', \$data)) \$product->set_sale_price(\$data['sale_price'] !== null ? (string) \$data['sale_price'] : '');
                if (array_key_exists('price', \$data)) \$product->set_price(\$data['price'] !== null ? (string) \$data['price'] : '');
                if (isset(\$data['stock_status'])) \$product->set_stock_status((string) \$data['stock_status']);
                if (array_key_exists('manage_stock', \$data)) \$product->set_manage_stock((bool) \$data['manage_stock']);
                if (array_key_exists('stock_quantity', \$data)) \$product->set_stock_quantity(\$data['stock_quantity'] !== null ? (int) \$data['stock_quantity'] : null);
                if (array_key_exists('weight', \$data)) \$product->set_weight(\$data['weight'] !== null ? (string) \$data['weight'] : '');
                if (array_key_exists('short_description', \$data)) \$product->set_short_description((string) \$data['short_description']);
                if (array_key_exists('description', \$data)) \$product->set_description((string) \$data['description']);

                \$savedId = \$product->save();
                \$applied['restored'][] = ['sku' => \$sku, 'id' => \$savedId];
            }
        } catch (\\Throwable \$e) {
            \$applied['errors'][] = ['action' => 'restore', 'sku' => \$sku, 'error' => \$e->getMessage()];
        }
    }
}

if (function_exists('wc_delete_product_transients')) {
    wc_delete_product_transients();
}
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

echo json_encode(['success' => true, 'applied' => \$applied], JSON_UNESCAPED_UNICODE);
PHP;
    }

    /**
     * Ejecuta el push de un changeset hacia el entorno destino.
     */
    public function push(string $projectRoot, array $manifest, string $targetEnv, bool $dryRun, OutputInterface $output): array
    {
        $entities = $manifest['entities'] ?? [];
        if (empty($entities)) {
            throw new \RuntimeException('El changeset no contiene entidades para aplicar.');
        }

        // 1. Crear backup previo de seguridad de las entidades afectadas en el destino
        $backupsDir = $this->getSyncDir($projectRoot) . '/backups';
        if (!is_dir($backupsDir)) {
            mkdir($backupsDir, 0755, true);
        }

        $output->writeln("<comment>Extrayendo estado previo en destino '{$targetEnv}' para backup atómico...</comment>");
        $targetCatalog = $this->fetchCatalog($targetEnv);

        $backupState = [];
        foreach ($entities as $sku => $entity) {
            if (isset($targetCatalog[$sku])) {
                $backupState[$sku] = $targetCatalog[$sku];
            }
        }

        $backupId = "backup_{$manifest['id']}_" . date('Ymd_His');
        $backupFile = "{$backupsDir}/{$backupId}.json";
        file_put_contents($backupFile, json_encode([
            'backup_id'        => $backupId,
            'changeset_id'     => $manifest['id'],
            'target_env'       => $targetEnv,
            'timestamp'        => date('c'),
            'saved_state'      => $backupState,
            'created_entities' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $output->writeln("<info>✓ Backup de seguridad guardado: {$backupId}.json</info>");

        if ($dryRun) {
            $output->writeln('<comment>[DRY-RUN] Simulación completa. Omitiendo inyección en destino.</comment>');
            return [
                'dry_run' => true,
                'backup_id' => $backupId,
                'simulated_count' => count($entities),
            ];
        }

        // 2. Inyección
        $output->writeln("<comment>Inyectando entidades de forma idempotente en '{$targetEnv}'...</comment>");
        $script = $this->generateInjectionScript($entities);
        $res = $this->executeInEnvironment($targetEnv, $script);

        if (!$res['success'] || !isset($res['data']['applied'])) {
            throw new \RuntimeException("Fallo durante la inyección en '{$targetEnv}': " . ($res['error'] ?? 'desconocido'));
        }

        $applied = $res['data']['applied'];
        $createdList = $applied['created'] ?? [];

        // Actualizar el archivo de backup con la lista exacta de entidades creadas
        file_put_contents($backupFile, json_encode([
            'backup_id'        => $backupId,
            'changeset_id'     => $manifest['id'],
            'target_env'       => $targetEnv,
            'timestamp'        => date('c'),
            'saved_state'      => $backupState,
            'created_entities' => $createdList,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'dry_run'   => false,
            'backup_id' => $backupId,
            'result'    => $applied,
        ];
    }

    /**
     * Revierte un backup atómico en el entorno destino.
     */
    public function rollback(string $projectRoot, string $backupId, string $targetEnv, OutputInterface $output): array
    {
        $backupsDir = $this->getSyncDir($projectRoot) . '/backups';
        $file = str_ends_with($backupId, '.json') ? $backupId : "{$backupsDir}/{$backupId}.json";
        if (!file_exists($file)) {
            throw new \RuntimeException("Archivo de backup '{$file}' no encontrado.");
        }

        $data = json_decode((string) file_get_contents($file), true);
        $savedState = $data['saved_state'] ?? [];
        $createdEntities = $data['created_entities'] ?? [];

        if (empty($savedState) && empty($createdEntities)) {
            $output->writeln('<comment>El backup no contenía entidades que restaurar ni revertir.</comment>');
            return ['restored' => 0, 'deleted' => 0];
        }

        $output->writeln("<comment>Ejecutando reversión en '{$targetEnv}' (Restaurando: " . count($savedState) . ", Eliminando nuevas: " . count($createdEntities) . ")...</comment>");
        $script = $this->generateRollbackScript($savedState, $createdEntities);
        $res = $this->executeInEnvironment($targetEnv, $script);

        if (!$res['success']) {
            throw new \RuntimeException("Error al revertir: " . ($res['error'] ?? 'desconocido'));
        }

        $applied = $res['data']['applied'] ?? [];
        return [
            'restored' => count($applied['restored'] ?? []),
            'deleted'  => count($applied['deleted'] ?? []),
            'result'   => $applied,
        ];
    }

    private function getComposeCommand(): array
    {
        $proc = new Process(['docker', 'compose', 'version']);
        $proc->run();
        return $proc->isSuccessful() ? ['docker', 'compose'] : ['docker-compose'];
    }
}
