<?php

namespace ElChoco\ErrorAnalyzer;

final class Redactor
{
    public function __construct(private string $base, private string $storage)
    {
    }

    public function text(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';
        $text = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $text) ?? '';
        $text = preg_replace('~\b(?:https?|ftp|smtp|smtps|mysql|pgsql|redis)://[^\s<>"\']+~i', '[url]', $text) ?? '';
        $text = preg_replace('/\b(?:Bearer|Basic)\s+[A-Za-z0-9+\/_=.\-]+/i', '[authorization]', $text) ?? '';
        $text = preg_replace('/\b(?:gh[pousr]_[A-Za-z0-9_]+|github_pat_[A-Za-z0-9_]+|sk-[A-Za-z0-9_\-]{12,}|AKIA[A-Z0-9]{16})\b/', '[secret]', $text) ?? '';
        $text = preg_replace('/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/', '[secret]', $text) ?? '';
        $text = preg_replace('/-----BEGIN [^-]*PRIVATE KEY-----[\s\S]*?(?:-----END [^-]*PRIVATE KEY-----|$)/', '[private key]', $text) ?? '';
        $key = '(?:[a-z0-9_.-]*(?:password|passwd|secret|token|api[_-]?key|authorization|cookie|session|username|user_name|user_id|email|recipient|address|ip_address|database|db_name|db_user)[a-z0-9_.-]*)';
        $text = preg_replace('/(["\']?\b'.$key.'["\']?\s*[:=]\s*)(?:"[^"\r\n]*"|\'[^\'\r\n]*\'|[^\s,;}]+)/i', '$1[redacted]', $text) ?? '';
        $text = preg_replace('/\b[A-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[email]', $text) ?? '';
        $text = preg_replace('/(?<![\w.])(?:\d{1,3}\.){3}\d{1,3}(?![\w.])/', '[ip]', $text) ?? '';
        $text = preg_replace_callback('/(?<![\w:])(?:[a-f0-9]{0,4}:){2,}[a-f0-9:.%]+/i', function (array $match): string {
            return filter_var($match[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[ip]' : $match[0];
        }, $text) ?? '';
        $text = preg_replace('/(?:\(?SQL:\s*|\bBindings:\s*)[^\r\n]*/i', '[SQL omitted]', $text) ?? '';
        foreach ([$this->storage => '[storage]', $this->base => '[flarum]'] as $path => $replacement) {
            if ($path === '') {
                continue;
            }
            $text = str_ireplace([$path, str_replace('\\', '/', $path), str_replace('/', '\\', $path)], $replacement, $text);
        }
        $text = preg_replace('~(?<![\w\]])(?:[A-Z]:[\\\\/]|/)(?:[^\s:()<>"\']+[\\\\/])+([^\s:()<>"\']+\.php)~i', '[path]/$1', $text) ?? '';
        $text = preg_replace('~(?:[A-Z]:[\\\\/]Users[\\\\/]|/(?:home|Users)/)[^/\\\\\s]+~i', '[home]', $text) ?? '';
        $text = preg_replace('/\b[a-f0-9]{32,}\b/i', '[identifier]', $text) ?? '';

        return trim($text);
    }
}
