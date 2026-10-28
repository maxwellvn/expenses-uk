<?php
declare(strict_types=1);

/** Proof files. Stored outside the web path under random names and only streamed through ClaimController::file. */
final class Receipt
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_FILES = 30;
    public const MAX_PER_LINE = 5;
    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
    ];

    public static function dir(): string
    {
        return ROOT . '/storage/receipts';
    }

    /** Normalises $_FILES['proof'] into a list, dropping empty slots. */
    public static function fromRequest(string $field = 'proof'): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || !is_array($f['name'])) return [];
        $out = [];
        foreach ($f['name'] as $i => $name) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $out[] = ['name' => $name, 'tmp' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }

    /** Per-line uploads from receipts[<line key>][], keyed by the line's form key. */
    public static function byLine(string $field = 'receipts'): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || !is_array($f['name'])) return [];
        $out = [];
        foreach ($f['name'] as $key => $names) {
            foreach ((array) $names as $n => $name) {
                if ($f['error'][$key][$n] === UPLOAD_ERR_NO_FILE) continue;
                $out[$key][] = ['name' => $name, 'tmp' => $f['tmp_name'][$key][$n], 'error' => $f['error'][$key][$n], 'size' => $f['size'][$key][$n]];
            }
        }
        return $out;
    }

    /** @return string|null error message for the user */
    public static function validate(array $uploads): ?string
    {
        if (count($uploads) > self::MAX_FILES) return 'Attach up to ' . self::MAX_FILES . ' files per claim.';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($uploads as $u) {
            $name = e($u['name']);
            if (in_array($u['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $u['size'] > self::MAX_BYTES) {
                return "$name is over 10 MB. Take a new photo at a lower resolution or export a smaller PDF.";
            }
            if ($u['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($u['tmp'])) return "$name did not upload. Try attaching it again.";
            if (!isset(self::TYPES[$finfo->file($u['tmp'])])) return "$name is not a photo or PDF. Attach a JPG, PNG, HEIC or PDF.";
        }
        return null;
    }

    /** Moves validated uploads into storage. @return array rows ready for claim_files */
    public static function store(array $uploads, string $uploaderId): array
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $rows = [];
        foreach ($uploads as $u) {
            $mime = $finfo->file($u['tmp']);
            $stored = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
            if (!move_uploaded_file($u['tmp'], self::dir() . '/' . $stored)) {
                self::discard($rows);
                throw new RuntimeException('Could not save upload');
            }
            $rows[] = [
                'original_name' => mb_substr(basename($u['name']), 0, 255),
                'stored_name' => $stored,
                'mime' => $mime,
                'size_bytes' => (int) $u['size'],
                'uploaded_by' => $uploaderId,
            ];
        }
        return $rows;
    }

    public static function discard(array $rows): void
    {
        foreach ($rows as $r) @unlink(self::dir() . '/' . basename($r['stored_name']));
    }
}
