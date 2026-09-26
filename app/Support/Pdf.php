<?php

namespace App\Support;

/**
 * A PDF, written by hand.
 *
 * **Why this exists rather than a package.** Every PHP PDF library is a
 * composer dependency, and this install deliberately has almost none — a hotel
 * that copies the folder onto a new machine and runs it should not first have
 * to get composer working through whatever the hotel's internet is doing today.
 * What a guest document actually needs is text in two weights, a rule, a filled
 * band and a right-aligned column of money, and that is a few hundred lines of
 * a well-documented file format rather than a dependency.
 *
 * The format, briefly: a PDF is numbered objects, a cross-reference table
 * saying what byte each one starts at, and a trailer pointing at the table.
 * Everything drawn lives in a content stream, in an operator language where
 * `BT … ET` brackets text, `Tf` picks a font, `Td` moves and `Tj` draws.
 * The page is 595x842 points (A4) with the origin at the BOTTOM left, so this
 * class counts `$y` down from the top and flips it when writing.
 *
 * Text is WinAnsi (Windows-1252), which is what the built-in fonts speak. UTF-8
 * is converted on the way in and anything the encoding cannot hold becomes a
 * near-equivalent — a rupee sign is written "Rs.", because a PDF that renders
 * a black box in the total is worse than one that spells it out.
 */
class Pdf
{
    public const A4_WIDTH = 595.28;

    public const A4_HEIGHT = 841.89;

    /** Every page's drawing commands, in order. */
    private array $pages = [];

    private string $stream = '';

    /** How far down the page the next thing goes, in points from the top. */
    private float $y = 0;

    private float $margin = 48;

    public function __construct(float $margin = 48)
    {
        $this->margin = $margin;
        $this->newPage();
    }

    /*
    |--------------------------------------------------------------------------
    | Drawing
    |--------------------------------------------------------------------------
    */

    public function newPage(): self
    {
        if ($this->stream !== '') {
            $this->pages[] = $this->stream;
        }

        $this->stream = '';
        $this->y = $this->margin;

        return $this;
    }

    /** Move down the page without drawing anything. */
    public function gap(float $points): self
    {
        $this->y += $points;

        return $this;
    }

    public function at(float $fromTop): self
    {
        $this->y = $fromTop;

        return $this;
    }

    public function cursor(): float
    {
        return $this->y;
    }

    public function left(): float
    {
        return $this->margin;
    }

    public function right(): float
    {
        return self::A4_WIDTH - $this->margin;
    }

    public function width(): float
    {
        return $this->right() - $this->left();
    }

    /**
     * One line of text, and the cursor moves past it.
     *
     * `$align` is left, right or center. `$x` and `$w` default to the text
     * column, so a right-aligned total lines up with the margin without the
     * caller working anything out.
     */
    public function text(
        string $value,
        float $size = 10,
        bool $bold = false,
        string $align = 'left',
        ?array $colour = null,
        ?float $x = null,
        ?float $w = null
    ): self {
        $x ??= $this->left();
        $w ??= $this->width();

        $this->y += $size;
        $this->write($value, $x, $w, $size, $bold, $align, $colour);
        $this->y += $size * 0.35;

        return $this;
    }

    /** Text placed exactly, leaving the cursor where it was. */
    public function textAt(
        string $value,
        float $x,
        float $fromTop,
        float $size = 10,
        bool $bold = false,
        string $align = 'left',
        ?array $colour = null,
        ?float $w = null
    ): self {
        $keep = $this->y;
        $this->y = $fromTop + $size;
        $this->write($value, $x, $w ?? ($this->right() - $x), $size, $bold, $align, $colour);
        $this->y = $keep;

        return $this;
    }

    /**
     * Text that wraps inside the column instead of running off the page.
     *
     * Returns the height it used, so a caller laying out two columns can keep
     * them level.
     */
    public function paragraph(string $value, float $size = 10, bool $bold = false, ?array $colour = null): self
    {
        foreach ($this->wrap($value, $this->width(), $size, $bold) as $line) {
            $this->text($line, $size, $bold, 'left', $colour);
        }

        return $this;
    }

    /** A hairline across the text column. */
    public function rule(?array $colour = null, float $thickness = 0.6): self
    {
        $colour ??= [0.85, 0.85, 0.87];
        $y = self::A4_HEIGHT - $this->y;

        $this->stream .= sprintf(
            "%.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S\n",
            $colour[0], $colour[1], $colour[2], $thickness,
            $this->left(), $y, $this->right(), $y
        );

        $this->y += 8;

        return $this;
    }

    /** A filled rectangle — the header band, a totals block. */
    public function box(float $x, float $fromTop, float $w, float $h, array $colour): self
    {
        $this->stream .= sprintf(
            "%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n",
            $colour[0], $colour[1], $colour[2],
            $x, self::A4_HEIGHT - $fromTop - $h, $w, $h
        );

        return $this;
    }

    /**
     * A label on the left and its value on the right — the shape every line of
     * a booking document takes.
     */
    public function row(string $label, string $value, float $size = 10, bool $strong = false): self
    {
        $top = $this->y;

        $this->textAt($label, $this->left(), $top, $size, false, 'left', [0.35, 0.35, 0.40], $this->width() * 0.5);
        $this->textAt($value, $this->left(), $top, $size, $strong, 'right', null, $this->width());

        $this->y += $size * 1.6;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Measuring
    |--------------------------------------------------------------------------
    */

    /** How wide a string is, in points, at this size. */
    public function textWidth(string $value, float $size, bool $bold = false): float
    {
        $widths = $bold ? self::BOLD_WIDTHS : self::WIDTHS;
        $total = 0;

        foreach (str_split($this->encode($value)) as $character) {
            $total += $widths[ord($character)] ?? 556;
        }

        return $total / 1000 * $size;
    }

    /**
     * Break a string into lines that fit a column.
     *
     * @return array<int, string>
     */
    public function wrap(string $value, float $columnWidth, float $size, bool $bold = false): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $value) ?: [] as $paragraph) {
            $current = '';

            foreach (preg_split('/\s+/', trim($paragraph)) ?: [] as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;

                if ($current !== '' && $this->textWidth($candidate, $size, $bold) > $columnWidth) {
                    $lines[] = $current;
                    $current = $word;

                    continue;
                }

                $current = $candidate;
            }

            $lines[] = $current;
        }

        return $lines;
    }

    /*
    |--------------------------------------------------------------------------
    | Finishing
    |--------------------------------------------------------------------------
    */

    /** The whole file, as a string. */
    public function render(): string
    {
        $pages = $this->pages;

        if ($this->stream !== '') {
            $pages[] = $this->stream;
        }

        if ($pages === []) {
            $pages[] = '';
        }

        /*
         * Object numbering: 1 is the catalogue, 2 the page tree, 3 and 4 the
         * two fonts, and then a page object and a content stream per page. The
         * cross-reference table at the end has to name the exact byte each
         * object starts at, which is why everything is assembled into one
         * string while the offsets are collected.
         */
        $objects = [];
        $first = 5;
        $kids = [];

        foreach ($pages as $index => $ignored) {
            $kids[] = ($first + $index * 2) . ' 0 R';
        }

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($pages) . " >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        foreach ($pages as $index => $content) {
            $pageId = $first + $index * 2;
            $streamId = $pageId + 1;

            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R "
                . sprintf('/MediaBox [0 0 %.2f %.2f] ', self::A4_WIDTH, self::A4_HEIGHT)
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> "
                . "/Contents {$streamId} 0 R >>";

            $objects[$streamId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";

        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function write(string $value, float $x, float $w, float $size, bool $bold, string $align, ?array $colour): void
    {
        $value = $this->encode($value);

        if ($value === '') {
            return;
        }

        if ($align === 'right') {
            $x = $x + $w - $this->textWidth($value, $size, $bold);
        } elseif ($align === 'center') {
            $x = $x + ($w - $this->textWidth($value, $size, $bold)) / 2;
        }

        $colour ??= [0.10, 0.10, 0.13];

        $this->stream .= sprintf(
            "BT %.3f %.3f %.3f rg /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $colour[0], $colour[1], $colour[2],
            $bold ? 'F2' : 'F1', $size,
            $x, self::A4_HEIGHT - $this->y,
            $this->escape($value)
        );
    }

    /** `(`, `)` and `\` end or escape a string literal, so they are escaped. */
    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $value);
    }

    /**
     * UTF-8 in, WinAnsi out.
     *
     * The characters a hotel document actually hits are the rupee sign, curly
     * quotes and dashes — all of them things a word processor inserts without
     * being asked. Each becomes its nearest printable equivalent rather than a
     * black box.
     */
    private function encode(string $value): string
    {
        $value = strtr($value, [
            '₹' => 'Rs.', '—' => '-', '–' => '-', '‘' => "'", '’' => "'",
            '“' => '"', '”' => '"', '…' => '...', '•' => '-', '→' => '->',
            '\u{00A0}' => ' ',
        ]);

        // //TRANSLIT turns an accented letter into its plain one where it can,
        // rather than dropping the whole word.
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);

        if ($converted === false) {
            // A build without the translit tables: keep the ASCII and drop the
            // rest, which still reads.
            $converted = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
        }

        return $converted;
    }

    /*
     * Helvetica's character widths, in thousandths of the font size, for the
     * printable ASCII range. Without these, right-aligning a total is a guess —
     * and a total that does not line up is the first thing anybody notices.
     * Indexed by byte value, starting at 32 (space).
     */
    private const WIDTHS = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556,
        112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722,
        120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584,
    ];

    private const BOLD_WIDTHS = [
        32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611,
        64 => 975, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
        96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
        104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611, 111 => 611,
        112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611, 118 => 556, 119 => 778,
        120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 280, 125 => 389, 126 => 584,
    ];
}
