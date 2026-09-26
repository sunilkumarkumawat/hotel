<?php

namespace App\Support;

use RuntimeException;

/**
 * A QR code, drawn as an SVG, with nothing installed.
 *
 * The table cards a guest scans to order from their phone have to work on a
 * hotel's own server — often one with no composer access and no image
 * extensions — so the code is generated here rather than fetched from a
 * service or a package. Nothing about a table card should depend on the
 * internet being up.
 *
 * Deliberately narrow: byte mode, error correction level M, versions 1 to 10.
 * That covers about 200 characters, which is far more than a URL needs, and it
 * keeps the class to one screen of tables rather than the whole specification.
 *
 * Written from ISO/IEC 18004. The interesting parts are marked; everything
 * else is the standard's own tables, which are not negotiable.
 */
class Qr
{
    /** Error correction level M: 15% of the code can be destroyed and still read. */
    private const EC_LEVEL_BITS = 0b00;

    /**
     * Per version: [total codewords, EC codewords per block, [[blocks, data codewords], …]].
     *
     * The two-entry form is for the versions whose blocks are not all the same
     * size — version 8 is two blocks of 38 and two of 39.
     */
    private const VERSIONS = [
        1 => [26, 10, [[1, 16]]],
        2 => [44, 16, [[1, 28]]],
        3 => [70, 26, [[1, 44]]],
        4 => [100, 18, [[2, 32]]],
        5 => [134, 24, [[2, 43]]],
        6 => [172, 16, [[4, 27]]],
        7 => [196, 18, [[4, 31]]],
        8 => [242, 22, [[2, 38], [2, 39]]],
        9 => [292, 22, [[3, 36], [2, 37]]],
        10 => [346, 26, [[4, 43], [1, 44]]],
    ];

    /** Where the little alignment squares go, by version. */
    private const ALIGNMENT = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /** Spare bits after the last codeword, by version. */
    private const REMAINDER_BITS = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0];

    /** @var array<int, array<int, int>> 0 or 1 per module */
    private array $modules = [];

    /** @var array<int, array<int, bool>> true where a function pattern lives */
    private array $reserved = [];

    private int $size;

    private function __construct(private string $text, private int $version)
    {
        $this->size = 17 + 4 * $version;

        for ($row = 0; $row < $this->size; $row++) {
            $this->modules[$row] = array_fill(0, $this->size, 0);
            $this->reserved[$row] = array_fill(0, $this->size, false);
        }
    }

    /**
     * The finished code as a square of 0s and 1s.
     *
     * @return array<int, array<int, int>>
     */
    public static function matrix(string $text): array
    {
        $qr = new self($text, self::versionFor($text));

        $qr->drawFunctionPatterns();
        $qr->placeData($qr->codewords());

        return $qr->applyBestMask();
    }

    /**
     * The code as an SVG string, ready to drop into a page or a print sheet.
     *
     * One `<path>` for every dark module rather than one rectangle each: a
     * version 4 code is 1,089 modules, and a thousand elements is a slow page
     * and a printer that thinks about it for a while.
     */
    public static function svg(string $text, int $size = 220, int $quiet = 4): string
    {
        $matrix = self::matrix($text);
        $count = count($matrix);
        $span = $count + $quiet * 2;

        $path = '';

        foreach ($matrix as $row => $cells) {
            foreach ($cells as $col => $on) {
                if ($on) {
                    $path .= sprintf('M%d %dh1v1h-1z', $col + $quiet, $row + $quiet);
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" '
            . 'shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="%d" height="%d" fill="#ffffff"/>'
            . '<path d="%s" fill="#000000"/></svg>',
            $size,
            $size,
            $span,
            $span,
            $span,
            $span,
            $path
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Turning text into codewords
    |--------------------------------------------------------------------------
    */

    /** The smallest version this text fits in. */
    private static function versionFor(string $text): int
    {
        $length = strlen($text);

        foreach (self::VERSIONS as $version => [, , $groups]) {
            $data = 0;

            foreach ($groups as [$blocks, $perBlock]) {
                $data += $blocks * $perBlock;
            }

            // 4 bits of mode, then the character count, then the bytes.
            $countBits = $version >= 10 ? 16 : 8;

            if (4 + $countBits + $length * 8 <= $data * 8) {
                return $version;
            }
        }

        throw new RuntimeException('That is too long for a table card QR code.');
    }

    /**
     * The data and error-correction codewords, interleaved the way the
     * standard wants them: first codeword of every block, then the second of
     * every block, and so on. Interleaving is what makes a stain across the
     * code damage a little of each block rather than destroying one outright.
     *
     * @return list<int>
     */
    private function codewords(): array
    {
        [$total, $ecPerBlock, $groups] = self::VERSIONS[$this->version];

        $bits = $this->bitstream();
        $dataWords = str_split($bits, 8);
        $dataWords = array_map(fn (string $byte) => bindec($byte), $dataWords);

        // Split into blocks, and give each its own error correction.
        $blocks = [];
        $ecBlocks = [];
        $at = 0;

        foreach ($groups as [$count, $perBlock]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($dataWords, $at, $perBlock);
                $at += $perBlock;

                $blocks[] = $block;
                $ecBlocks[] = self::errorCorrection($block, $ecPerBlock);
            }
        }

        $out = [];

        $longest = max(array_map('count', $blocks));

        for ($i = 0; $i < $longest; $i++) {
            foreach ($blocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        if (count($out) !== $total) {
            throw new RuntimeException('QR codeword count came out wrong — this is a bug.');
        }

        return $out;
    }

    /** Mode, length, the bytes, a terminator and padding — as a string of '0's and '1's. */
    private function bitstream(): string
    {
        [, , $groups] = self::VERSIONS[$this->version];

        $dataWords = 0;

        foreach ($groups as [$blocks, $perBlock]) {
            $dataWords += $blocks * $perBlock;
        }

        $capacity = $dataWords * 8;
        $countBits = $this->version >= 10 ? 16 : 8;

        $bits = '0100';                                                   // byte mode
        $bits .= str_pad(decbin(strlen($this->text)), $countBits, '0', STR_PAD_LEFT);

        foreach (str_split($this->text) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        // Up to four zeros to say "that is the end of the message".
        $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));

        // Then round up to a whole byte.
        if ($remainder = strlen($bits) % 8) {
            $bits .= str_repeat('0', 8 - $remainder);
        }

        // And fill what is left with the two pad bytes, alternating.
        $pads = ['11101100', '00010001'];
        $i = 0;

        while (strlen($bits) < $capacity) {
            $bits .= $pads[$i++ % 2];
        }

        return $bits;
    }

    /**
     * Reed–Solomon error correction over GF(256).
     *
     * @param  list<int>  $data
     * @return list<int>
     */
    private static function errorCorrection(array $data, int $count): array
    {
        [$exp, $log] = self::galois();

        // The generator polynomial for `count` error correction codewords.
        $generator = [1];

        for ($i = 0; $i < $count; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);

            foreach ($generator as $index => $coefficient) {
                $next[$index] ^= $coefficient;

                // Multiply by (x - α^i), which in GF(2) is (x + α^i).
                $next[$index + 1] ^= $coefficient === 0
                    ? 0
                    : $exp[($log[$coefficient] + $i) % 255];
            }

            $generator = $next;
        }

        $remainder = array_merge($data, array_fill(0, $count, 0));

        for ($i = 0; $i < count($data); $i++) {
            $lead = $remainder[$i];

            if ($lead === 0) {
                continue;
            }

            $factor = $log[$lead];

            foreach ($generator as $index => $coefficient) {
                if ($coefficient === 0) {
                    continue;
                }

                $remainder[$i + $index] ^= $exp[($log[$coefficient] + $factor) % 255];
            }
        }

        return array_values(array_slice($remainder, count($data)));
    }

    /**
     * Exponent and log tables for GF(256) with the QR primitive 0x11D.
     *
     * @return array{0: list<int>, 1: array<int, int>}
     */
    private static function galois(): array
    {
        static $tables = null;

        if ($tables !== null) {
            return $tables;
        }

        $exp = array_fill(0, 256, 0);
        $log = array_fill(0, 256, 0);
        $value = 1;

        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;

            $value <<= 1;

            if ($value & 0x100) {
                $value ^= 0x11D;
            }
        }

        return $tables = [$exp, $log];
    }

    /*
    |--------------------------------------------------------------------------
    | Drawing
    |--------------------------------------------------------------------------
    */

    private function drawFunctionPatterns(): void
    {
        $last = $this->size - 7;

        $this->finder(0, 0);
        $this->finder(0, $last);
        $this->finder($last, 0);

        // Timing: the dotted lines that tell a scanner how big a module is.
        for ($i = 8; $i < $this->size - 8; $i++) {
            $this->set(6, $i, $i % 2 === 0 ? 1 : 0, true);
            $this->set($i, 6, $i % 2 === 0 ? 1 : 0, true);
        }

        $this->alignment();

        // The one module that is always dark, for reasons the standard does
        // not explain and does not have to.
        $this->set(4 * $this->version + 9, 8, 1, true);

        $this->reserveFormatAreas();
    }

    private function finder(int $row, int $col): void
    {
        // The 7×7 eye, plus the white separator around it.
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                if (! $this->inside($row + $r, $col + $c)) {
                    continue;
                }

                $edge = $r === 0 || $r === 6 || $c === 0 || $c === 6;
                $core = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                $outside = $r < 0 || $r > 6 || $c < 0 || $c > 6;

                $this->set($row + $r, $col + $c, (! $outside && ($edge || $core)) ? 1 : 0, true);
            }
        }
    }

    private function alignment(): void
    {
        $centres = self::ALIGNMENT[$this->version];
        $first = $centres[0] ?? null;
        $last = $centres ? $centres[count($centres) - 1] : null;

        foreach ($centres as $row) {
            foreach ($centres as $col) {
                /*
                 * Exactly three combinations are skipped — the ones a finder
                 * is already sitting in. Everything else is drawn, *including*
                 * the ones that land on the timing lines: from version 7 those
                 * exist, and leaving them out is a code no scanner can read.
                 * (Their own modules agree with the timing pattern where they
                 * cross it, so overwriting is harmless.)
                 */
                $corner = ($row === $first && $col === $first)
                    || ($row === $first && $col === $last)
                    || ($row === $last && $col === $first);

                if ($corner) {
                    continue;
                }

                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $ring = abs($r) === 2 || abs($c) === 2;
                        $centre = $r === 0 && $c === 0;

                        $this->set($row + $r, $col + $c, ($ring || $centre) ? 1 : 0, true);
                    }
                }
            }
        }
    }

    /** Keep the format and version strips clear until they are written. */
    private function reserveFormatAreas(): void
    {
        for ($i = 0; $i < 9; $i++) {
            $this->reserve(8, $i);
            $this->reserve($i, 8);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->reserve(8, $this->size - 1 - $i);
            $this->reserve($this->size - 1 - $i, 8);
        }

        if ($this->version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $this->reserve($this->size - 11 + $j, $i);
                    $this->reserve($i, $this->size - 11 + $j);
                }
            }
        }
    }

    /**
     * Lay the codewords out in the zigzag the standard specifies: two columns
     * at a time, right to left, alternating up and down, skipping column 6
     * because the vertical timing pattern is in it.
     *
     * @param  list<int>  $codewords
     */
    private function placeData(array $codewords): void
    {
        $bits = '';

        foreach ($codewords as $word) {
            $bits .= str_pad(decbin($word), 8, '0', STR_PAD_LEFT);
        }

        $bits .= str_repeat('0', self::REMAINDER_BITS[$this->version]);

        $at = 0;
        $upward = true;

        for ($right = $this->size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }

            for ($step = 0; $step < $this->size; $step++) {
                $row = $upward ? $this->size - 1 - $step : $step;

                foreach ([$right, $right - 1] as $col) {
                    if ($this->reserved[$row][$col]) {
                        continue;
                    }

                    $this->modules[$row][$col] = $at < strlen($bits) ? (int) $bits[$at] : 0;
                    $at++;
                }
            }

            $upward = ! $upward;
        }
    }

    /**
     * Try all eight masks, keep the one the standard's penalty rules like best.
     *
     * The masks exist to break up runs and large blocks of one colour, which is
     * what a scanner finds hard. Picking the best one is not an optimisation —
     * an unmasked code of mostly-white data is genuinely unreadable.
     *
     * @return array<int, array<int, int>>
     */
    private function applyBestMask(): array
    {
        $base = $this->modules;
        $best = null;
        $bestScore = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $this->modules = $base;

            for ($row = 0; $row < $this->size; $row++) {
                for ($col = 0; $col < $this->size; $col++) {
                    if ($this->reserved[$row][$col]) {
                        continue;
                    }

                    if (self::maskApplies($mask, $row, $col)) {
                        $this->modules[$row][$col] ^= 1;
                    }
                }
            }

            $this->writeFormat($mask);
            $this->writeVersion();

            $score = $this->penalty();

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $this->modules;
            }
        }

        return $best;
    }

    private static function maskApplies(int $mask, int $row, int $col): bool
    {
        return match ($mask) {
            0 => ($row + $col) % 2 === 0,
            1 => $row % 2 === 0,
            2 => $col % 3 === 0,
            3 => ($row + $col) % 3 === 0,
            4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
            5 => ($row * $col) % 2 + ($row * $col) % 3 === 0,
            6 => (($row * $col) % 2 + ($row * $col) % 3) % 2 === 0,
            7 => (($row + $col) % 2 + ($row * $col) % 3) % 2 === 0,
        };
    }

    /** The 15 bits saying which error correction level and which mask, written twice. */
    private function writeFormat(int $mask): void
    {
        $data = (self::EC_LEVEL_BITS << 3) | $mask;
        $bits = $data << 10;

        for ($i = 14; $i >= 10; $i--) {
            if ($bits & (1 << $i)) {
                $bits ^= 0b10100110111 << ($i - 10);
            }
        }

        $format = (($data << 10) | $bits) ^ 0b101010000010010;

        for ($i = 0; $i < 15; $i++) {
            $bit = ($format >> $i) & 1;

            // Copy one: down column 8 for the first nine bits, then left
            // along row 8 for the rest. Row 6 and column 6 are timing, so the
            // sequence steps over them.
            if ($i < 6) {
                $this->modules[$i][8] = $bit;
            } elseif ($i === 6) {
                $this->modules[7][8] = $bit;
            } elseif ($i === 7) {
                $this->modules[8][8] = $bit;
            } elseif ($i === 8) {
                $this->modules[8][7] = $bit;
            } else {
                $this->modules[8][14 - $i] = $bit;
            }

            // Copy two: split between the other two eyes, so damage to one
            // corner never takes the format information with it.
            if ($i < 8) {
                $this->modules[8][$this->size - 1 - $i] = $bit;
            } else {
                $this->modules[$this->size - 15 + $i][8] = $bit;
            }
        }
    }

    /** Versions 7 and up also say their own version number, in two corners. */
    private function writeVersion(): void
    {
        if ($this->version < 7) {
            return;
        }

        $bits = $this->version << 12;

        for ($i = 17; $i >= 12; $i--) {
            if ($bits & (1 << $i)) {
                $bits ^= 0b1111100100101 << ($i - 12);
            }
        }

        $value = ($this->version << 12) | $bits;

        for ($i = 0; $i < 18; $i++) {
            $bit = ($value >> $i) & 1;
            $row = intdiv($i, 3);
            $col = $i % 3;

            $this->modules[$this->size - 11 + $col][$row] = $bit;
            $this->modules[$row][$this->size - 11 + $col] = $bit;
        }
    }

    /**
     * The standard's four penalty rules, added up. Lower is better.
     *
     * They punish, in order: long runs of one colour, 2×2 blocks of one colour,
     * patterns that look like a finder, and a code that is mostly one colour.
     */
    private function penalty(): int
    {
        $score = 0;
        $n = $this->size;

        // Rule 1 — runs of five or more.
        foreach ([true, false] as $horizontal) {
            for ($a = 0; $a < $n; $a++) {
                $run = 1;

                for ($b = 1; $b < $n; $b++) {
                    $here = $horizontal ? $this->modules[$a][$b] : $this->modules[$b][$a];
                    $before = $horizontal ? $this->modules[$a][$b - 1] : $this->modules[$b - 1][$a];

                    if ($here === $before) {
                        $run++;

                        continue;
                    }

                    if ($run >= 5) {
                        $score += 3 + ($run - 5);
                    }

                    $run = 1;
                }

                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
            }
        }

        // Rule 2 — 2×2 blocks of one colour.
        for ($row = 0; $row < $n - 1; $row++) {
            for ($col = 0; $col < $n - 1; $col++) {
                $value = $this->modules[$row][$col];

                if ($value === $this->modules[$row][$col + 1]
                    && $value === $this->modules[$row + 1][$col]
                    && $value === $this->modules[$row + 1][$col + 1]) {
                    $score += 3;
                }
            }
        }

        // Rule 3 — anything that reads like a finder pattern.
        $needles = [
            [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0],
            [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1],
        ];

        foreach ([true, false] as $horizontal) {
            for ($a = 0; $a < $n; $a++) {
                for ($b = 0; $b <= $n - 11; $b++) {
                    foreach ($needles as $needle) {
                        $match = true;

                        for ($i = 0; $i < 11; $i++) {
                            $value = $horizontal
                                ? $this->modules[$a][$b + $i]
                                : $this->modules[$b + $i][$a];

                            if ($value !== $needle[$i]) {
                                $match = false;

                                break;
                            }
                        }

                        if ($match) {
                            $score += 40;
                        }
                    }
                }
            }
        }

        // Rule 4 — how far from half dark the whole code is.
        $dark = 0;

        foreach ($this->modules as $row) {
            $dark += array_sum($row);
        }

        $percent = $dark * 100 / ($n * $n);
        $score += (int) (abs($percent - 50) / 5) * 10;

        return $score;
    }

    /*
    |--------------------------------------------------------------------------
    | Small helpers
    |--------------------------------------------------------------------------
    */

    private function set(int $row, int $col, int $value, bool $reserve = false): void
    {
        if (! $this->inside($row, $col)) {
            return;
        }

        $this->modules[$row][$col] = $value;

        if ($reserve) {
            $this->reserved[$row][$col] = true;
        }
    }

    private function reserve(int $row, int $col): void
    {
        if ($this->inside($row, $col)) {
            $this->reserved[$row][$col] = true;
        }
    }

    private function inside(int $row, int $col): bool
    {
        return $row >= 0 && $col >= 0 && $row < $this->size && $col < $this->size;
    }
}
