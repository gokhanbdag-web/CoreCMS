<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $columns = $driver === 'sqlite'
        ? array_column($db->query('PRAGMA table_info(core_media)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($db->query('SHOW COLUMNS FROM core_media')->fetchAll(PDO::FETCH_ASSOC), 'Field');

    $definitions = [
        'file_size' => $driver === 'sqlite' ? 'INTEGER NOT NULL DEFAULT 0' : 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
        'width' => $driver === 'sqlite' ? 'INTEGER NULL' : 'INT UNSIGNED NULL',
        'height' => $driver === 'sqlite' ? 'INTEGER NULL' : 'INT UNSIGNED NULL',
        'alt_text' => $driver === 'sqlite' ? 'VARCHAR(255) NOT NULL DEFAULT ""' : 'VARCHAR(255) NOT NULL DEFAULT ""',
        'uploaded_by' => $driver === 'sqlite' ? 'INTEGER NULL' : 'BIGINT UNSIGNED NULL',
    ];
    foreach ($definitions as $name => $definition) {
        if (!in_array($name, $columns, true)) {
            $db->exec('ALTER TABLE core_media ADD COLUMN ' . $name . ' ' . $definition);
        }
    }
};
