<?php

namespace ElChoco\ErrorAnalyzer;

final class PackageInventory
{
    public function read(string $vendor, array $enabled): array
    {
        $file = $vendor.'/composer/installed.json';
        $contents = is_file($file) ? @file_get_contents($file, false, null, 0, 8388609) : false;
        if ($contents === false || strlen($contents) > 8388608) {
            return ['packages' => [], 'available' => false];
        }
        $data = json_decode($contents, true, 32);
        $entries = $data['packages'] ?? $data;
        if (!is_array($entries)) {
            return ['packages' => [], 'available' => false];
        }
        $packages = [];
        foreach ($entries as $entry) {
            $name = $entry['name'] ?? '';
            if (!is_string($name) || !preg_match('~^[a-z0-9_.-]+/[a-z0-9_.-]+$~D', $name)) {
                continue;
            }
            $type = ($entry['type'] ?? '') === 'flarum-extension' ? 'extension' : 'library';
            if (in_array($name, ['flarum/core', 'flarum/framework'], true)) {
                $type = 'core';
            }
            $id = \Flarum\Extension\Extension::nameToId($name);
            $version = $entry['pretty_version'] ?? $entry['version'] ?? 'unknown';
            $version = is_string($version) && preg_match('/^[a-zA-Z0-9.*+_\/\- ]{1,100}$/D', $version) ? $version : 'unknown';
            $paths = [$vendor.'/'.$name];
            $installed = $entry['install-path'] ?? null;
            if (is_string($installed) && $installed !== '') {
                $absolute = preg_match('~^(?:/|[a-z]:[\\\\/])~i', $installed);
                $paths[] = $absolute ? $installed : $vendor.'/composer/'.$installed;
            }
            $packages[$name] = ['name' => $name, 'version' => $version, 'type' => $type,
                'enabled' => $type === 'core' || in_array($id, $enabled, true),
                'paths' => array_map([$this, 'normalize'], $paths)];
        }
        ksort($packages);

        return ['packages' => $packages, 'available' => true];
    }

    public function mentioned(string $body, array $packages): array
    {
        $body = strtolower(preg_replace('~/+~', '/', str_replace('\\', '/', $body)) ?? '');
        $matches = [];
        foreach ($packages as $package) {
            foreach ($package['paths'] as $path) {
                if ($path !== '' && str_contains($body, strtolower($path).'/')) {
                    unset($package['paths']);
                    $matches[$package['name']] = $package;
                    break;
                }
            }
        }

        return array_values($matches);
    }

    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return (str_starts_with($path, '/') ? '/' : '').implode('/', $parts);
    }
}
