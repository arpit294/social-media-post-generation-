<?php

namespace App\Services;

class ReplyText
{
    public static function forChat(string $text): string
    {
        return self::emojisOnHookOnly(self::withoutAsterisks(self::readablePost($text)));
    }

    public static function withoutAsterisks(string $text): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];

        foreach ($lines as &$line) {
            $line = str_replace(['**', '__'], '', $line);

            if (preg_match('/^\s*\*\s+/', $line) === 1) {
                $line = preg_replace('/^\s*\*\s+/', '', $line, 1) ?? $line;
            }

            $line = str_replace('*', '', $line);
        }

        return implode("\n", $lines);
    }

    public static function emojisOnHookOnly(string $text): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $hookKept = false;

        foreach ($lines as &$line) {
            if (trim($line) === '') {
                continue;
            }

            if (! $hookKept) {
                $hookKept = true;

                continue;
            }

            $line = trim((string) preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $line));
        }

        return implode("\n", $lines);
    }

    private static function readablePost(string $text): string
    {
        $decoded = self::decodeObject($text);

        if (is_array($decoded)) {
            $post = $decoded['post_text'] ?? null;

            if (is_string($post) && trim($post) !== '') {
                return trim($post);
            }

            $built = self::captionFrom($decoded);

            if ($built !== '') {
                return $built;
            }
        }

        $salvaged = self::captionFrom(self::salvageFields($text));

        if ($salvaged !== '') {
            return $salvaged;
        }

        return self::withoutJsonBlock($text);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeObject(string $text): ?array
    {
        $trimmed = trim($text);
        $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $trimmed) ?? $trimmed;
        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function captionFrom(array $data): string
    {
        $parts = [];
        $hook = $data['selected_hook'] ?? null;

        if (is_array($hook)) {
            foreach (['line_1', 'line_2'] as $key) {
                if (is_string($hook[$key] ?? null) && trim($hook[$key]) !== '') {
                    $parts[] = trim($hook[$key]);
                }
            }
        } elseif (is_string($data['hit_line'] ?? null) && trim($data['hit_line']) !== '') {
            $parts[] = trim($data['hit_line']);
        }

        foreach (['specific_moment', 'problem', 'no_blame_turn', 'custom_software_line', 'closing_content'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        if ($parts === []) {
            return '';
        }

        $parts[] = 'Webexpert Infotech | Custom software for manufacturers.';
        $tags = $data['hashtags'] ?? null;

        if (is_array($tags) && $tags !== []) {
            $parts[] = implode(' ', array_map(strval(...), $tags));
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private static function salvageFields(string $text): array
    {
        $fields = [];

        foreach (['hit_line', 'specific_moment', 'problem', 'no_blame_turn', 'custom_software_line', 'closing_content'] as $key) {
            $value = self::jsonString($text, $key);

            if ($value !== null) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    private static function jsonString(string $text, string $key): ?string
    {
        $pattern = '/"'.preg_quote($key, '/').'"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        $value = json_decode('"'.$matches[1].'"');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function withoutJsonBlock(string $text): string
    {
        $stripped = preg_replace('/```(?:json)?\s*[\s\S]*$/i', '', $text) ?? $text;
        $stripped = preg_replace('/\{\s*"platform"[\s\S]*$/i', '', $stripped) ?? $stripped;
        $stripped = trim($stripped);

        return $stripped !== '' ? $stripped : $text;
    }
}
