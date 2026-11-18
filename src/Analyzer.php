<?php

namespace ElChoco\ErrorAnalyzer;

final class Analyzer
{
    public const VERSION = '1.0.0';
    private const LEVELS = ['DEBUG' => 100, 'INFO' => 200, 'NOTICE' => 250, 'WARNING' => 300,
        'ERROR' => 400, 'CRITICAL' => 500, 'ALERT' => 550, 'EMERGENCY' => 600];
    private const MAX_BYTES = 8388608;
    private const MAX_RECORDS = 2500;
    private const MAX_GROUPS = 200;

    public function scan(string $base, string $storage, string $vendor, array $enabled, int $maxFiles, string $minimum): array
    {
        $inventory = new PackageInventory();
        $installed = $inventory->read($vendor, $enabled);
        $redactor = new Redactor($base, $storage);
        $parser = new LogParser();
        $files = [];
        $warnings = [];
        $folder = $storage.'/logs';
        $directory = realpath($folder);
        if (!$directory || !is_dir($directory) || !is_readable($directory)) {
            $warnings[] = 'logs_unavailable';
        } else {
            try {
                $visited = 0;
                foreach (new \DirectoryIterator($directory) as $file) {
                    if (++$visited > 2000) {
                        $warnings[] = 'directory_limit';
                        break;
                    }
                    if (!$file->isLink() && $file->isFile() && strtolower($file->getExtension()) === 'log') {
                        $files[] = ['path' => $file->getPathname(), 'time' => $file->getMTime()];
                    }
                }
            } catch (\UnexpectedValueException $exception) {
                $warnings[] = 'logs_unavailable';
            }
        }
        usort($files, fn (array $left, array $right): int => ($right['time'] <=> $left['time']) ?: strcmp($left['path'], $right['path']));
        $groups = [];
        $bytes = 0;
        $records = 0;
        $matched = 0;
        $scanned = 0;
        $partial = count($files) > $maxFiles;
        foreach (array_slice($files, 0, $maxFiles) as $file) {
            if ($bytes >= self::MAX_BYTES || $records >= self::MAX_RECORDS) {
                $partial = true;
                break;
            }
            $path = $file['path'];
            if (is_link($path) || dirname((string) realpath($path)) !== $directory) {
                $warnings[] = 'file_skipped';
                continue;
            }
            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                $warnings[] = 'file_skipped';
                continue;
            }
            $size = fstat($handle)['size'] ?? 0;
            $length = min(1048576, self::MAX_BYTES - $bytes, $size);
            $offset = max(0, $size - $length);
            $partial = $partial || $offset > 0;
            fseek($handle, $offset);
            $contents = stream_get_contents($handle, $length);
            fclose($handle);
            if ($contents === false) {
                $warnings[] = 'file_skipped';
                continue;
            }
            $scanned++;
            $bytes += strlen($contents);
            if ($offset > 0) {
                $newline = strpos($contents, "\n");
                $contents = $newline === false ? '' : substr($contents, $newline + 1);
            }
            $events = $parser->parse($contents);
            if ($contents !== '' && $events === []) {
                $warnings[] = 'unrecognized_log';
            }
            foreach (array_reverse($events) as $event) {
                if ($records >= self::MAX_RECORDS) {
                    $partial = true;
                    break;
                }
                $records++;
                if ((self::LEVELS[$event['level']] ?? 0) < (self::LEVELS[$minimum] ?? 300)) {
                    continue;
                }
                $excerpt = $parser->excerpt($event['body']);
                $message = $redactor->text($excerpt['message']);
                $frames = array_map([$redactor, 'text'], $excerpt['frames']);
                $packages = $inventory->mentioned($excerpt['message']."\n".implode("\n", $excerpt['frames']), $installed['packages']);
                $signature = preg_replace('/\b(?:[a-f0-9]{8}-[a-f0-9-]{27,}|\d+)\b/i', '<n>', $message."\n".implode("\n", array_slice($frames, 0, 2)));
                $id = hash('sha256', $event['level']."\n".$signature."\n".implode(',', array_column($packages, 'name')));
                if (!isset($groups[$id]) && count($groups) >= self::MAX_GROUPS) {
                    $partial = true;
                    continue;
                }
                $time = $this->timestamp($event['time']);
                $matched++;
                if (!isset($groups[$id])) {
                    $groups[$id] = ['id' => $id, 'level' => $event['level'], 'count' => 0, 'message' => $message,
                        'first' => $time, 'last' => $time, 'sample' => implode("\n", array_merge([$message], $frames)),
                        'packages' => [], 'files' => []];
                }
                $group = &$groups[$id];
                $group['count']++;
                if ($time > $group['last']) {
                    $group['message'] = $message;
                    $group['sample'] = implode("\n", array_merge([$message], $frames));
                }
                $group['first'] = min($group['first'], $time);
                $group['last'] = max($group['last'], $time);
                $group['files'][$redactor->text(basename($path))] = true;
                foreach ($packages as $package) {
                    $group['packages'][$package['name']] = $package;
                }
                unset($group);
            }
        }
        if (!$installed['available']) {
            $warnings[] = 'inventory_unavailable';
        }
        foreach ($groups as &$group) {
            $group['packages'] = array_values($group['packages']);
            $group['files'] = array_keys($group['files']);
        }
        unset($group);
        usort($groups, fn (array $left, array $right): int => strcmp($right['last'], $left['last']));
        $extensions = [];
        $core = 'unknown';
        foreach ($installed['packages'] as $package) {
            if ($package['type'] === 'core') {
                $core = $package['version'];
            }
            if ($package['type'] === 'extension') {
                unset($package['paths']);
                $extensions[] = $package;
            }
        }

        return ['version' => self::VERSION, 'generatedAt' => gmdate('c'), 'groups' => array_values($groups),
            'environment' => ['flarum' => $core, 'phpWeb' => PHP_VERSION, 'extensions' => $extensions],
            'scan' => ['filesFound' => count($files), 'filesScanned' => $scanned, 'bytes' => $bytes,
                'records' => $records, 'matched' => $matched, 'partial' => $partial,
                'minimum' => $minimum, 'maxFiles' => $maxFiles, 'warnings' => array_values(array_unique($warnings))]];
    }

    private function timestamp(string $value): string
    {
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $exception) {
            return 'unknown';
        }
    }
}
