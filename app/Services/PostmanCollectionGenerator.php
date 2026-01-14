<?php

namespace App\Services;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PostmanCollectionGenerator
{
    /**
     * Generate a Postman v2.1 collection JSON array from Laravel routes.
     *
     * @param  iterable<LaravelRoute>  $routes
     * @param  array{baseUrl?:string,name?:string,include?:string}  $options
     */
    public function generate(iterable $routes, array $options = []): array
    {
        $name = $options['name'] ?? 'Dabablane API (Generated)';
        $baseUrl = $options['baseUrl'] ?? '{{baseUrl}}';
        $include = $options['include'] ?? 'api'; // 'api' | 'all'

        $items = [];
        foreach ($routes as $route) {
            if (!$route instanceof LaravelRoute) {
                continue;
            }

            $uri = ltrim($route->uri(), '/');
            if ($include === 'api' && !Str::startsWith($uri, 'api/')) {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            if (count($methods) === 0) {
                continue;
            }

            // Ignore closures (they're usually not public API docs material)
            $action = $route->getActionName();
            if ($action === 'Closure') {
                continue;
            }

            foreach ($methods as $method) {
                $this->addRouteItem($items, $method, $uri, $route, $baseUrl);
            }
        }

        // Deterministic order (folders + requests)
        $items = $this->sortItems($items);

        return [
            'info' => [
                'name' => $name,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            // Apply bearer token auth globally; harmless for public endpoints.
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{token}}', 'type' => 'string'],
                ],
            ],
            'item' => $items,
        ];
    }

    /**
     * Write JSON file with pretty printing.
     */
    public function writeJson(string $path, array $data): void
    {
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Extract "method uri" signatures from a Postman collection JSON file for comparison.
     *
     * @return array<string, true>
     */
    public function extractSignaturesFromCollection(string $path): array
    {
        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $set = [];
        $walk = function (array $items) use (&$walk, &$set) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (isset($item['item']) && is_array($item['item'])) {
                    $walk($item['item']);
                    continue;
                }
                $method = Arr::get($item, 'request.method');
                $url = Arr::get($item, 'request.url');
                $raw = is_string($url) ? $url : Arr::get($url, 'raw');
                if (!is_string($method) || !is_string($raw)) {
                    continue;
                }

                // Normalize raw url into a comparable "api/..." uri when possible
                $normalized = $this->normalizeRawUrlToUri($raw);
                if ($normalized === null) {
                    continue;
                }
                $set[strtoupper($method) . ' ' . $normalized] = true;
            }
        };

        $walk(Arr::get($decoded, 'item', []));
        return $set;
    }

    private function normalizeRawUrlToUri(string $raw): ?string
    {
        // Typical forms:
        // - http://your-api-url/api/back/v1/...
        // - {{baseUrl}}/api/back/v1/...
        // - https://domain.tld/api/...
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Strip scheme/host if present
        $raw = preg_replace('~^https?://[^/]+~i', '', $raw);

        // Strip baseUrl variable if present
        $raw = preg_replace('~^\\{\\{baseUrl\\}\\}~', '', $raw);

        $raw = ltrim($raw, '/');
        if (!Str::startsWith($raw, 'api/')) {
            return null;
        }

        // Drop query string
        $raw = explode('?', $raw, 2)[0];

        // Replace Postman variables back to {param} for stable comparison
        $raw = preg_replace('~\\{\\{([a-zA-Z0-9_]+)\\}\\}~', '{$1}', $raw);

        return $raw;
    }

    private function addRouteItem(array &$rootItems, string $method, string $uri, LaravelRoute $route, string $baseUrl): void
    {
        [$topFolder, $subFolder] = $this->foldersFor($route, $uri);

        $folderRef = &$this->getOrCreateFolder($rootItems, $topFolder);
        if ($subFolder !== null) {
            $folderRef = &$this->getOrCreateFolder($folderRef['item'], $subFolder);
        }

        $folderRef['item'][] = $this->makeRequestItem($method, $uri, $route, $baseUrl);
    }

    /**
     * @return array{0:string,1:?string}
     */
    private function foldersFor(LaravelRoute $route, string $uri): array
    {
        // Prefer URI-based grouping (matches how routes are structured in this repo)
        if (Str::startsWith($uri, 'api/front/')) {
            return ['Front', $this->controllerGroupName($route) ?? 'Misc'];
        }
        if (Str::startsWith($uri, 'api/back/')) {
            return ['Back', $this->controllerGroupName($route) ?? 'Misc'];
        }
        if (Str::startsWith($uri, 'api/admin/')) {
            return ['Admin', $this->controllerGroupName($route) ?? 'Misc'];
        }
        if (Str::startsWith($uri, 'api/terms-conditions')) {
            return ['Terms & Conditions', null];
        }

        return ['Other', $this->controllerGroupName($route) ?? 'Misc'];
    }

    private function controllerGroupName(LaravelRoute $route): ?string
    {
        $action = $route->getActionName();
        if (!is_string($action) || $action === '' || $action === 'Closure') {
            return null;
        }
        if (!str_contains($action, '@')) {
            return null;
        }
        [$class] = explode('@', $action, 2);
        $base = class_basename($class);
        return Str::replaceLast('Controller', '', $base);
    }

    /**
     * @return array{name:string,item:array}
     */
    private function &getOrCreateFolder(array &$items, string $name): array
    {
        foreach ($items as &$item) {
            if (is_array($item) && ($item['name'] ?? null) === $name && isset($item['item']) && is_array($item['item'])) {
                return $item;
            }
        }

        $items[] = [
            'name' => $name,
            'item' => [],
        ];

        return $items[array_key_last($items)];
    }

    private function makeRequestItem(string $method, string $uri, LaravelRoute $route, string $baseUrl): array
    {
        $displayName = $this->displayNameFor($method, $uri, $route);

        $headers = [
            ['key' => 'Accept', 'value' => 'application/json'],
        ];

        $body = null;
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
            $body = [
                'mode' => 'raw',
                'raw' => '{}',
            ];
        }

        // Replace {param} with {{param}} for Postman variables
        $rawPath = preg_replace('~\\{([a-zA-Z0-9_]+)\\}~', '{{$1}}', $uri);

        $request = [
            'method' => strtoupper($method),
            'header' => $headers,
            'url' => $baseUrl . '/' . ltrim($rawPath, '/'),
            'description' => $this->descriptionFor($route),
        ];

        if ($body !== null) {
            $request['body'] = $body;
        }

        return [
            'name' => $displayName,
            'request' => $request,
            'response' => [],
        ];
    }

    private function displayNameFor(string $method, string $uri, LaravelRoute $route): string
    {
        $action = $route->getActionName();
        $method = strtoupper($method);
        $short = $uri;

        // Use route name if present; otherwise fall back to METHOD + URI
        $name = $route->getName();
        if (is_string($name) && $name !== '') {
            return $method . ' ' . $name;
        }

        // Slightly shorter display: drop leading "api/"
        if (Str::startsWith($short, 'api/')) {
            $short = substr($short, 4);
        }

        // Append controller@method when useful
        if (is_string($action) && $action !== '' && $action !== 'Closure') {
            $actionShort = Str::of($action)->afterLast('\\')->toString();
            return $method . ' ' . $short . '  (' . $actionShort . ')';
        }

        return $method . ' ' . $short;
    }

    private function descriptionFor(LaravelRoute $route): string
    {
        $action = $route->getActionName();
        $middleware = $route->gatherMiddleware();
        $middleware = array_values(array_unique(array_filter($middleware, fn ($m) => is_string($m) && $m !== '')));

        $lines = [];
        if (is_string($action) && $action !== '' && $action !== 'Closure') {
            $lines[] = 'Action: ' . $action;
        }
        if (count($middleware) > 0) {
            $lines[] = 'Middleware: ' . implode(', ', $middleware);
        }
        return implode("\n", $lines);
    }

    private function sortItems(array $items): array
    {
        usort($items, function ($a, $b) {
            $aIsFolder = isset($a['item']) && is_array($a['item']);
            $bIsFolder = isset($b['item']) && is_array($b['item']);
            if ($aIsFolder !== $bIsFolder) {
                return $aIsFolder ? -1 : 1;
            }
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        foreach ($items as &$item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $item['item'] = $this->sortItems($item['item']);
            }
        }

        return $items;
    }
}

