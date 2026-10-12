<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\Sync;

use Symfony\Component\Process\Process;

class EntityComparatorService
{
    /**
     * Genera el script PHP seguro para extraer entidades de catálogo con fingerprinting.
     */
    public function getCatalogExtractorScript(): string
    {
        return <<<'PHP'
if (!function_exists('wc_get_products')) {
    echo json_encode(['error' => 'WooCommerce no está activo']);
    exit(0);
}

$products = wc_get_products(['limit' => -1, 'status' => 'any']);
$data = [];

foreach ($products as $p) {
    if (!$p instanceof \WC_Product) {
        continue;
    }
    $id = $p->get_id();
    $sku = $p->get_sku() ?: 'sku_auto_' . $p->get_slug();
    $slug = $p->get_slug();
    
    // Categorías por slug
    $cats = [];
    $terms = get_the_terms($id, 'product_cat');
    if (is_array($terms)) {
        foreach ($terms as $t) {
            $cats[] = ['slug' => $t->slug, 'name' => $t->name];
        }
    }

    $item = [
        'id'                => $id,
        'sku'               => $sku,
        'slug'              => $slug,
        'name'              => $p->get_name(),
        'type'              => $p->get_type(),
        'status'            => $p->get_status(),
        'price'             => (float) $p->get_price(),
        'regular_price'     => (float) $p->get_regular_price(),
        'sale_price'        => $p->get_sale_price() ? (float) $p->get_sale_price() : null,
        'manage_stock'      => $p->get_manage_stock(),
        'stock_quantity'    => $p->get_stock_quantity(),
        'stock_status'      => $p->get_stock_status(),
        'weight'            => (float) $p->get_weight(),
        'short_description' => $p->get_short_description(),
        'description'       => $p->get_description(),
        'categories'        => $cats,
        'meta'              => [
            '_vendor_id' => get_post_field('post_author', $id),
        ],
    ];

    // Fingerprint determinista (excluye id de base de datos)
    $fingerprintPayload = $item;
    unset($fingerprintPayload['id']);
    ksort($fingerprintPayload);
    $item['fingerprint'] = md5(json_encode($fingerprintPayload, JSON_UNESCAPED_UNICODE));

    $data[$sku] = $item;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
PHP;
    }

    /**
     * Compara dos colecciones de entidades indexadas por SKU o clave única.
     * 
     * @param array<string, array> $sourceEntities
     * @param array<string, array> $targetEntities
     * @return array{new: array, modified: array, deleted: array, identical: array}
     */
    public function compare(array $sourceEntities, array $targetEntities): array
    {
        $new = [];
        $modified = [];
        $identical = [];
        $deleted = [];

        foreach ($sourceEntities as $key => $src) {
            if (!isset($targetEntities[$key])) {
                $new[$key] = [
                    'entity' => $src,
                    'status' => 'NEW',
                ];
                continue;
            }

            $tgt = $targetEntities[$key];
            if ($src['fingerprint'] === $tgt['fingerprint']) {
                $identical[$key] = [
                    'source' => $src,
                    'target' => $tgt,
                    'status' => 'IDENTICAL',
                ];
            } else {
                // Calcular diferencias a nivel de campo
                $diffs = $this->computeFieldDiff($src, $tgt);
                if (empty($diffs)) {
                    $identical[$key] = [
                        'source' => $src,
                        'target' => $tgt,
                        'status' => 'IDENTICAL',
                    ];
                } else {
                    $modified[$key] = [
                        'source' => $src,
                        'target' => $tgt,
                        'diffs'  => $diffs,
                        'status' => 'MODIFIED',
                    ];
                }
            }
        }

        foreach ($targetEntities as $key => $tgt) {
            if (!isset($sourceEntities[$key])) {
                $deleted[$key] = [
                    'entity' => $tgt,
                    'status' => 'ONLY_IN_TARGET',
                ];
            }
        }

        return [
            'new'       => $new,
            'modified'  => $modified,
            'deleted'   => $deleted,
            'identical' => $identical,
        ];
    }

    /**
     * Calcula las discrepancias campo por campo entre origen y destino.
     */
    public function computeFieldDiff(array $source, array $target): array
    {
        $diffs = [];
        $compareKeys = [
            'name', 'slug', 'price', 'regular_price', 'sale_price',
            'stock_quantity', 'stock_status', 'manage_stock', 'weight',
            'status', 'short_description', 'description', 'categories'
        ];

        foreach ($compareKeys as $key) {
            $srcVal = $source[$key] ?? null;
            $tgtVal = $target[$key] ?? null;

            if ($key === 'categories') {
                $srcCats = array_column($srcVal ?: [], 'slug');
                $tgtCats = array_column($tgtVal ?: [], 'slug');
                sort($srcCats);
                sort($tgtCats);
                if ($srcCats !== $tgtCats) {
                    $diffs[$key] = [
                        'from' => $tgtCats,
                        'to'   => $srcCats,
                    ];
                }
                continue;
            }

            if ($srcVal !== $tgtVal) {
                $diffs[$key] = [
                    'from' => $tgtVal,
                    'to'   => $srcVal,
                ];
            }
        }

        if (($source['meta'] ?? []) !== ($target['meta'] ?? [])) {
            $diffs['meta'] = [
                'from' => $target['meta'] ?? [],
                'to'   => $source['meta'] ?? [],
            ];
        }

        return $diffs;
    }
}
