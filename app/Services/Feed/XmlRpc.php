<?php

namespace App\Services\Feed;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Minimal, hardened XML-RPC codec (methodCall parser + methodResponse serializer).
 * No DTDs (any DOCTYPE is rejected), no entity substitution, no network access, size and depth limited.
 */
final class XmlRpc
{
    public const MAX_BYTES = 2_000_000;

    private const MAX_DEPTH = 16;

    /** @return array{0: string, 1: array} method name and params */
    public static function parseCall(string $xml): array
    {
        if ($xml === '' || strlen($xml) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Request empty or too large.');
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new InvalidArgumentException('DTD is not allowed.');
        }
        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        try {
            $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
        if (! $ok || $doc->doctype !== null || $doc->documentElement?->nodeName !== 'methodCall') {
            throw new InvalidArgumentException('Malformed XML-RPC request.');
        }
        $root = $doc->documentElement;
        $name = trim((string) self::child($root, 'methodName')?->textContent);
        if (! preg_match('/^[A-Za-z0-9_.]{1,64}$/', $name)) {
            throw new InvalidArgumentException('Invalid method name.');
        }
        $params = [];
        if ($p = self::child($root, 'params')) {
            foreach (self::children($p, 'param') as $param) {
                $value = self::child($param, 'value');
                if (! $value) {
                    throw new InvalidArgumentException('Param without value.');
                }
                $params[] = self::decode($value, 0);
            }
        }

        return [$name, $params];
    }

    public static function response(mixed $value): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n<methodResponse><params><param>".self::encode($value).'</param></params></methodResponse>';
    }

    public static function fault(int $code, string $message): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n<methodResponse><fault>"
            .self::encode(['faultCode' => $code, 'faultString' => $message]).'</fault></methodResponse>';
    }

    public static function encode(mixed $v): string
    {
        $inner = match (true) {
            $v === null => '<nil/>',
            is_bool($v) => '<boolean>'.($v ? 1 : 0).'</boolean>',
            is_int($v) => '<int>'.$v.'</int>',
            is_float($v) => '<double>'.$v.'</double>',
            $v instanceof \DateTimeInterface => '<dateTime.iso8601>'.$v->format('Ymd\TH:i:s').'</dateTime.iso8601>',
            is_array($v) && array_is_list($v) && $v !== [] => '<array><data>'.implode('', array_map(self::encode(...), $v)).'</data></array>',
            is_array($v) && $v === [] => '<array><data></data></array>',
            is_array($v) => '<struct>'.implode('', array_map(
                fn ($k, $x) => '<member><name>'.htmlspecialchars((string) $k, ENT_XML1).'</name>'.self::encode($x).'</member>',
                array_keys($v), $v)).'</struct>',
            default => '<string>'.htmlspecialchars((string) $v, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8').'</string>',
        };

        return '<value>'.$inner.'</value>';
    }

    private static function decode(DOMElement $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Nesting too deep.');
        }
        $typed = null;
        foreach ($value->childNodes as $n) {
            if ($n instanceof DOMElement) {
                $typed = $n;
                break;
            }
        }
        if (! $typed) {
            return $value->textContent; // untyped value = string
        }
        $t = $typed->textContent;

        return match ($typed->nodeName) {
            'string' => $t,
            'int', 'i4', 'i8' => preg_match('/^\s*[+-]?\d{1,18}\s*$/', $t) ? (int) $t : throw new InvalidArgumentException('Bad int.'),
            'boolean' => in_array(trim($t), ['1', '0'], true) ? trim($t) === '1' : throw new InvalidArgumentException('Bad boolean.'),
            'double' => is_numeric(trim($t)) ? (float) $t : throw new InvalidArgumentException('Bad double.'),
            'dateTime.iso8601' => trim($t),
            'base64' => base64_decode(preg_replace('/\s+/', '', $t), true) ?: '',
            'nil' => null,
            'array' => array_map(fn ($v) => self::decode($v, $depth + 1), self::children(self::child($typed, 'data') ?? $typed, 'value')),
            'struct' => self::decodeStruct($typed, $depth),
            default => throw new InvalidArgumentException('Unknown type.'),
        };
    }

    private static function decodeStruct(DOMElement $s, int $depth): array
    {
        $out = [];
        foreach (self::children($s, 'member') as $m) {
            $name = self::child($m, 'name');
            $val = self::child($m, 'value');
            if (! $name || ! $val) {
                throw new InvalidArgumentException('Bad struct member.');
            }
            $out[trim($name->textContent)] = self::decode($val, $depth + 1);
        }

        return $out;
    }

    private static function child(DOMElement $el, string $name): ?DOMElement
    {
        return self::children($el, $name)[0] ?? null;
    }

    /** @return DOMElement[] */
    private static function children(DOMElement $el, string $name): array
    {
        $out = [];
        foreach ($el->childNodes as $n) {
            if ($n instanceof DOMElement && $n->nodeName === $name) {
                $out[] = $n;
            }
        }

        return $out;
    }
}
