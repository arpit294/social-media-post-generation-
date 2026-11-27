<?php

namespace App\Services;

use Illuminate\Support\Str;

class PostIntent
{
    public function isPost(string $text): bool
    {
        $text = trim($text);

        if ($text === '' || preg_match('/\bjson\b/i', $text) === 1) {
            return false;
        }

        if ($this->isRevision($text)) {
            return true;
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

    public function isRevision(string $text): bool
    {
        $text = Str::of($text)->lower()->squish()->toString();

        return preg_match('/\b(upgrade|improve|improved|improving|better|stronger|rewrite|revise|revised|polish|enhance|sharpen|update|updated|updating|change|tweak|redraft|modify)\b/u', $text) === 1
            && preg_match('/\b(post|caption|content|version|hook|scene|this|it|detail|details)\b/u', $text) === 1;
    }
}
