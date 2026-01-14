<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Services\PostmanCollectionGenerator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/**
 * Generate Postman collections directly from registered Laravel routes.
 *
 * Usage:
 * - php artisan postman:generate
 * - php artisan postman:generate --compare
 * - php artisan postman:generate --output=collectionpostman.generated.json
 * - php artisan postman:generate --baseUrl=http://localhost:8000
 */
Artisan::command('postman:generate {--output=collectionpostman.generated.json} {--baseUrl=} {--include=api} {--compare}', function () {
    /** @var string $output */
    $output = (string) $this->option('output');
    /** @var string|null $baseUrl */
    $baseUrl = $this->option('baseUrl');
    /** @var string $include */
    $include = (string) $this->option('include');
    /** @var bool $compare */
    $compare = (bool) $this->option('compare');

    $generator = app(PostmanCollectionGenerator::class);

    $routes = app('router')->getRoutes();
    $collection = $generator->generate($routes, [
        'name' => 'Dabablane API (Generated from routes)',
        'baseUrl' => ($baseUrl && trim($baseUrl) !== '') ? rtrim(trim($baseUrl), '/') : '{{baseUrl}}',
        'include' => in_array($include, ['api', 'all'], true) ? $include : 'api',
    ]);

    $outputPath = base_path($output);
    $generator->writeJson($outputPath, $collection);
    $this->info('Wrote Postman collection: ' . $outputPath);

    // Also write convenience split collections (Front/Back) if present
    $items = $collection['item'] ?? [];
    if (is_array($items)) {
        foreach (['Front' => 'collectionpostmanFront.generated.json', 'Back' => 'collectionpostmanBack.generated.json'] as $folderName => $fileName) {
            $folder = collect($items)->first(fn ($i) => is_array($i) && ($i['name'] ?? null) === $folderName);
            if (is_array($folder)) {
                $split = $collection;
                $split['info']['name'] = 'Dabablane API - ' . $folderName . ' (Generated from routes)';
                $split['item'] = [$folder];
                $splitPath = base_path($fileName);
                $generator->writeJson($splitPath, $split);
                $this->info('Wrote Postman collection: ' . $splitPath);
            }
        }
    }

    // Write a basic environment template (optional but helpful)
    $envPath = base_path('postman.environment.generated.json');
    $env = [
        'name' => 'Dabablane (Generated)',
        'values' => [
            [
                'key' => 'baseUrl',
                'value' => ($baseUrl && trim($baseUrl) !== '') ? rtrim(trim($baseUrl), '/') : 'http://localhost:8000',
                'type' => 'default',
                'enabled' => true,
            ],
            [
                'key' => 'token',
                'value' => '',
                'type' => 'secret',
                'enabled' => true,
            ],
        ],
        '_postman_variable_scope' => 'environment',
        '_postman_exported_at' => now()->toIso8601String(),
        '_postman_exported_using' => 'Laravel routes/console.php postman:generate',
    ];
    File::put($envPath, json_encode($env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->info('Wrote Postman environment: ' . $envPath);

    if ($compare) {
        $existing = [
            'collectionpostman.json',
            'collectionpostmanFront.json',
            'collectionpostmanBack.json',
        ];

        $generatedSigs = [];
        foreach (($collection['item'] ?? []) as $top) {
            // We'll just reuse the generator's extractor by writing to a temp file? Not needed:
        }
        // Build signatures from generated collection by writing to disk and re-reading (simple + consistent)
        $generatedSigs = $generator->extractSignaturesFromCollection($outputPath);

        foreach ($existing as $file) {
            $path = base_path($file);
            $existingSigs = $generator->extractSignaturesFromCollection($path);
            if (count($existingSigs) === 0) {
                $this->warn("Compare: {$file} not found or empty/unparseable.");
                continue;
            }

            $missing = array_diff_key($generatedSigs, $existingSigs);
            $extra = array_diff_key($existingSigs, $generatedSigs);

            $this->line('');
            $this->info("Compare results vs {$file}:");
            $this->line('  Generated endpoints: ' . count($generatedSigs));
            $this->line('  Existing endpoints:  ' . count($existingSigs));
            $this->line('  Missing in existing: ' . count($missing));
            $this->line('  Extra in existing:   ' . count($extra));

            if (count($missing) > 0) {
                $this->line('');
                $this->warn('First 50 missing endpoints (METHOD uri):');
                foreach (array_slice(array_keys($missing), 0, 50) as $sig) {
                    $this->line('  - ' . $sig);
                }
            }
        }
    }
})->purpose('Generate Postman collection(s) from routes');
