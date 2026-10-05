<?php

/**
 * Обрабатывает загруженное фото товара (поле формы "image").
 * Возвращает публичный URL нового файла, null если файл не передан,
 * либо бросает RuntimeException с понятным сообщением об ошибке.
 */
function handle_image_upload(): ?string {
    if (empty($_FILES['image']['name'])) {
        return null;
    }
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Ошибка загрузки файла');
    }
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Файл слишком большой (максимум 5 МБ)');
    }

    $tmpPath = $_FILES['image']['tmp_name'];
    // getimagesize() проверяет, что это реально изображение, а не просто файл
    // с подходящим расширением — так безопаснее, чем доверять имени файла.
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        throw new RuntimeException('Файл должен быть изображением');
    }

    $extByType = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $ext = $extByType[$info[2]] ?? null;
    if (!$ext) {
        throw new RuntimeException('Поддерживаются только JPG, PNG, GIF и WEBP');
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        throw new RuntimeException('Не удалось создать папку для загрузок');
    }

    $filename = uniqid('img_', true) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $dest)) {
        throw new RuntimeException('Не удалось сохранить файл на сервере');
    }

    return UPLOAD_URL . '/' . $filename;
}

/** Удаляет файл товара с диска по его публичному URL (если он существует). */
function delete_uploaded_image(?string $imageUrl): void {
    if (!$imageUrl) return;
    $path = UPLOAD_DIR . '/' . basename($imageUrl);
    if (is_file($path)) {
        @unlink($path);
    }
}
