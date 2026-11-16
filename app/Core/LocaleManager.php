<?php
declare(strict_types=1);

namespace CoreCMS\\Core;

use PDO;
use RuntimeException;

final class LocaleManager
{
    public function __construct(private PDO $db) {}

    private static function validCode(string $code): bool
    {
        return (bool)preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $code);
    }

    public function languages(): array
    {
        return $this->db->query('SELECT locale_code,name,is_default,enabled FROM core_languages ORDER BY is_default DESC,name,locale_code')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function defaultCode(): string
    {
        $value = $this->db->query('SELECT locale_code FROM core_languages WHERE is_default=1 AND enabled=1 LIMIT 1')->fetchColumn();
        return is_string($value) && $value !== '' ? $value : 'tr';
    }

    public function saveLanguage(string $code, string $name, bool $enabled = true): void
    {
        $code = trim($code);
        $name = trim($name);
        if (!self::validCode($code)) throw new RuntimeException('Dil kodu geçersiz. Örnek: tr, en veya en-US.');
        if ($name === '' || preg_match_all('/./us', $name) > 80) throw new RuntimeException('Dil adı zorunludur ve 80 karakteri aşamaz.');
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO core_languages(locale_code,name,is_default,enabled) VALUES(?,?,0,?) ON CONFLICT(locale_code) DO UPDATE SET name=excluded.name,enabled=excluded.enabled'
            : 'INSERT INTO core_languages(locale_code,name,is_default,enabled) VALUES(?,?,0,?) ON DUPLICATE KEY UPDATE name=VALUES(name),enabled=VALUES(enabled)';
        $this->db->prepare($sql)->execute([$code, $name, $enabled ? 1 : 0]);
    }

    public function setDefault(string $code): void
    {
        if (!self::validCode($code)) throw new RuntimeException('Varsayılan dil kodu geçersiz.');
        $check = $this->db->prepare('SELECT enabled FROM core_languages WHERE locale_code=?');
        $check->execute([$code]);
        if ((int)$check->fetchColumn() !== 1) throw new RuntimeException('Varsayılan dil etkin bir dil olmalıdır.');
        $this->db->beginTransaction();
        try {
            $this->db->exec('UPDATE core_languages SET is_default=0');
            $this->db->prepare('UPDATE core_languages SET is_default=1 WHERE locale_code=?')->execute([$code]);
            $this->db->commit();
        } catch (\\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function saveTranslation(string $locale, string $key, string $value): void
    {
        if (!self::validCode($locale)) throw new RuntimeException('Çeviri dili geçersiz.');
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,189}$/', $key)) throw new RuntimeException('Çeviri anahtarı geçersiz.');
        if (preg_match_all('/./us', $value) > 10000) throw new RuntimeException('Çeviri metni 10.000 karakteri aşamaz.');
        $check = $this->db->prepare('SELECT enabled FROM core_languages WHERE locale_code=?');
        $check->execute([$locale]);
        if ((int)$check->fetchColumn() !== 1) throw new RuntimeException('Çeviri eklemek için dil etkin olmalıdır.');
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON CONFLICT(locale_code,translation_key) DO UPDATE SET translation_value=excluded.translation_value,updated_at=CURRENT_TIMESTAMP'
            : 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE translation_value=VALUES(translation_value),updated_at=CURRENT_TIMESTAMP';
        $this->db->prepare($sql)->execute([$locale, $key, $value]);
    }

    public function translations(string $locale, string $query = ''): array
    {
        if (!self::validCode($locale)) return [];
        if ($query !== '') {
            $stmt = $this->db->prepare('SELECT translation_key,translation_value FROM core_translations WHERE locale_code=? AND (translation_key LIKE ? OR translation_value LIKE ?) ORDER BY translation_key LIMIT 500');
            $needle = '%' . $query . '%';
            $stmt->execute([$locale, $needle, $needle]);
        } else {
            $stmt = $this->db->prepare('SELECT translation_key,translation_value FROM core_translations WHERE locale_code=? ORDER BY translation_key LIMIT 500');
            $stmt->execute([$locale]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function translate(string $key, ?string $locale = null, ?string $fallback = null): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,189}$/', $key)) return $fallback ?? $key;
        $locale = $locale ?? $this->defaultCode();
        if (!self::validCode($locale)) $locale = $this->defaultCode();
        $stmt = $this->db->prepare('SELECT translation_value FROM core_translations WHERE locale_code=? AND translation_key=?');
        $stmt->execute([$locale, $key]);
        $value = $stmt->fetchColumn();
        if (is_string($value)) return $value;
        return $fallback ?? $key;
    }
}
