<?php
/**
 * Secure upload handling for past-question papers.
 */

declare(strict_types=1);

class UploadException extends Exception
{
}

/**
 * Validates and stores an uploaded file from a $_FILES[...] entry.
 *
 * @return array{file_path: string, original_filename: string, mime_type: string, file_size: int, file_hash: string}
 */
function store_uploaded_file(array $file, int $courseId): array
{
    $config = app_config()['upload'];

    if (!isset($file['error']) || is_array($file['error'])) {
        throw new UploadException('Invalid upload.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new UploadException(upload_error_message((int) $file['error']));
    }

    if ($file['size'] > $config['max_size_bytes']) {
        $maxMb = (int) ($config['max_size_bytes'] / (1024 * 1024));
        throw new UploadException("File is too large. Maximum size is {$maxMb}MB.");
    }

    // Never trust the client-supplied MIME type - detect it from the file's real content.
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realMime, $config['allowed_mimes'], true)) {
        throw new UploadException('Unsupported file type. Upload a PDF, DOCX, JPG, or PNG.');
    }

    $hash = hash_file('sha256', $file['tmp_name']);

    $extension = match ($realMime) {
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        default => 'bin',
    };

    $folder = rtrim($config['path'], '/') . '/' . $courseId;
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new UploadException('Could not create the upload folder.');
    }

    $storedName  = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $folder . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new UploadException('Could not save the uploaded file.');
    }

    return [
        'file_path'         => $courseId . '/' . $storedName, // relative to the uploads root
        'original_filename' => basename((string) $file['name']),
        'mime_type'         => $realMime,
        'file_size'         => (int) $file['size'],
        'file_hash'         => $hash,
    ];
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than this server allows (check php.ini upload_max_filesize).',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Try again.',
        UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
        default => 'Upload failed. Try again.',
    };
}
