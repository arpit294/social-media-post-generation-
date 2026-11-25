<?php

namespace App\Services;

class PostIntent
{
    public function isPost(string $text): bool
    {
        $text = trim($text);

        if ($text === '' || preg_match('/\bjson\b/i', $text) === 1) {
            return false;
        }

        if (preg_match('/\b(generate|write|create|draft)\b.{0,40}\bpost\b/iu', $text) === 1) {
            return true;
        }

        if (preg_match('/\?\s*$/u', $text) === 1) {
            return false;
        }

        if (preg_match('/^(how|what|why|when|who|which|can|could|should|would|is|are|do|does|did|will|where)\b/iu', $text) === 1) {
            return false;
        }

        return mb_strlen($text) >= 12;
    }
}
