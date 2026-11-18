<?php

namespace ElChoco\ErrorAnalyzer;

final class LogParser
{
    private const HEADER = '/^\[(\d{4}-\d{2}-\d{2}[ T][^\]]+)\]\s+(.+?)\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):\s*(.*)$/';
    private const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function parse(string $text): array
    {
        $records = [];
        $current = null;
        foreach (explode("\n", $text) as $line) {
            $line = rtrim($line, "\r");
            $json = $this->jsonRecord($line);
            if ($json !== null || preg_match(self::HEADER, $line, $matches)) {
                if ($current !== null) {
                    $records[] = $current;
                }
                $current = $json ?? ['time' => $matches[1], 'level' => $matches[3], 'body' => $matches[4]];
            } elseif ($current !== null && strlen($current['body']) < 65536) {
                $current['body'] .= "\n".substr($line, 0, 65536 - strlen($current['body']));
            }
        }
        if ($current !== null) {
            $records[] = $current;
        }

        return $records;
    }

    public function excerpt(string $body): array
    {
        $lines = explode("\n", str_replace('\\n', "\n", $body));
        $message = preg_split('/\s+(?:\{["\']|\[\])/', $lines[0], 2)[0];
        $frames = [];
        if (preg_match('/\bat ([^\r\n()]+\.php):(\d+)/', $body, $origin)) {
            $frames[] = '#origin '.$origin[1].':'.$origin[2];
        }
        foreach ($lines as $line) {
            if (preg_match('/^\s*(#\d+)\s+(.+)$/', $line, $match)) {
                $frame = preg_replace('/(\):\s*[^\r\n(]+)\(.*$/', '$1(…)', $match[2]) ?? '';
                $frame = preg_replace('/^([A-Za-z_\\\\{][A-Za-z0-9_\\\\:>{}\-]*)\(.*$/', '$1(…)', $frame) ?? '';
                $frame = preg_replace('/\{main\}.*/', '{main}', $frame) ?? '';
                $frames[] = $match[1].' '.substr($frame, 0, 700);
            }
            if (count($frames) === 18) {
                break;
            }
        }

        return ['message' => substr($message, 0, 3000), 'frames' => $frames];
    }

    private function jsonRecord(string $line): ?array
    {
        if (!str_starts_with(ltrim($line), '{')) {
            return null;
        }
        $value = json_decode($line, true, 24);
        if (!is_array($value) || !is_string($value['message'] ?? null)) {
            return null;
        }
        $level = is_string($value['level_name'] ?? null) ? strtoupper($value['level_name']) : '';
        if (!in_array($level, self::LEVELS, true)) {
            return null;
        }
        $datetime = $value['datetime'] ?? null;
        $time = is_array($datetime) ? ($datetime['date'] ?? '') : $datetime;
        if (!is_string($time)) {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]/', $time)) {
            return null;
        }
        $body = $value['message'];
        $context = is_array($value['context'] ?? null) ? $value['context'] : [];
        $exception = $context['exception'] ?? null;
        if (is_array($exception)) {
            if (is_string($exception['file'] ?? null)) {
                $body .= "\n#0 ".$exception['file'];
            }
            if (is_array($exception['trace'] ?? null)) {
                foreach (array_slice($exception['trace'], 0, 18) as $index => $frame) {
                    if (is_string($frame)) {
                        $body .= "\n#".($index + 1).' '.preg_replace('/^#\d+\s+/', '', $frame);
                    }
                }
            }
        } elseif (is_string($exception)) {
            $body .= "\n".$exception;
        }

        return ['time' => $time, 'level' => $level, 'body' => substr($body, 0, 65536)];
    }
}
