<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;
use DateTimeZone;

final class SiteSettings
{
    private const DEFAULTS = [
        'site_name' => 'CoreCMS',
        'site_language' => 'tr',
        'timezone' => 'Europe/Istanbul',
        'mail_from_name' => '',
        'mail_from_email' => '',
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_security' => 'starttls',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_timeout' => '15',
        'home_eyebrow' => 'MODÜLER İÇERİK YÖNETİMİ',
        'home_title' => 'Tek çekirdek.',
        'home_accent_title' => 'Sınırsız sektör.',
        'home_description' => 'CoreCMS kurulumu tamamlandı. Sektör modülleri kendi sayfalarını, iş akışlarını ve tasarım katmanlarını bağımsız olarak ekler.',
        'site_url' => '',
        'seo_org_name' => '',
        'seo_logo_url' => '',
        'seo_social_image' => '',
        'search_console_verification' => '',
    ];

    public function __construct(private PDO $db) {}

    public function all(): array
    {
        $values = self::DEFAULTS;
        $stmt = $this->db->query('SELECT setting_key,setting_value FROM core_settings');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['setting_key'], $values)) $values[$row['setting_key']] = (string)$row['setting_value'];
        }
        return $values;
    }

    private static function length(string $value): int
    {
        return preg_match_all('/./us', $value, $matches) ?: 0;
    }

    public function save(array $input): void
    {
        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = trim((string)($input[$key] ?? $default));
            $limit = match ($key) {
                'site_name' => 120,
                'site_language' => 12,
                'timezone' => 64,
                'mail_from_name' => 160,
                'mail_from_email' => 190,\n                'smtp_enabled' => 1,\n                'smtp_host' => 255,\n                'smtp_port' => 5,\n                'smtp_security' => 12,\n                'smtp_username' => 190,\n                'smtp_password' => 500,\n                'smtp_timeout' => 2,
                'home_eyebrow' => 100,
                'home_title', 'home_accent_title' => 120,
                'home_description' => 600,
                'site_url' => 500,
                'seo_org_name' => 160,
                'seo_logo_url' => 1000,
                'seo_social_image' => 1000,
                'search_console_verification' => 200,
                default => 120,
            };
            if (self::length($value) > $limit) throw new RuntimeException('Ayar alanı izin verilen uzunluğu aşıyor: ' . $key);
            $values[$key] = $value;
        }
        if ($values['site_name'] === '' || $values['home_title'] === '') throw new RuntimeException('Site adı ve ana sayfa başlığı boş bırakılamaz.');
        if (!preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $values['site_language'])) throw new RuntimeException('Dil kodu geçersiz.');
        if (!in_array($values['timezone'], DateTimeZone::listIdentifiers(), true)) throw new RuntimeException('Saat dilimi geçersiz.');
        if ($values['mail_from_email'] !== '' && !filter_var($values['mail_from_email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Gönderici e-posta adresi geçersiz.');\n        if (!in_array($values['smtp_enabled'], ['0','1'], true)) throw new RuntimeException('SMTP etkinlik ayarı geçersiz.');\n        if (!preg_match('/^[0-9]{1,5}$/', $values['smtp_port']) || (int)$values['smtp_port'] < 1 || (int)$values['smtp_port'] > 65535) throw new RuntimeException('SMTP portu geçersiz.');\n        if (!in_array($values['smtp_security'], ['none','starttls','ssl'], true)) throw new RuntimeException('SMTP güvenlik türü geçersiz.');
        if (!preg_match('/^[0-9]{1,2}$/', $values['smtp_timeout']) || (int)$values['smtp_timeout'] < 5 || (int)$values['smtp_timeout'] > 60) throw new RuntimeException('SMTP zaman aşımı 5–60 saniye arasında olmalıdır.');
        if ($values['smtp_enabled'] === '1') {\n            if ($values['smtp_host'] === '' || preg_match('/[\\\\r\\\\n\\\\s\\\\/]/', $values['smtp_host'])) throw new RuntimeException('SMTP sunucu adresi geçersiz.');
            if ($values['mail_from_email'] === '') throw new RuntimeException('SMTP için gönderici e-posta adresi gereklidir.');
            if (preg_match('/[\\\\r\\\\n]/', $values['smtp_username']) || preg_match('/[\\\\r\\\\n]/', $values['smtp_password'])) throw new RuntimeException('SMTP kimlik bilgileri geçersiz karakter içeriyor.');
            if ($values['smtp_username'] !== '' && $values['smtp_password'] === '' && trim((string)($this->all()['smtp_password'] ?? '')) === '') throw new RuntimeException('SMTP parolası gereklidir.');\n        }
        if (preg_match('/[\\r\\n]/', $values['mail_from_name'])) throw new RuntimeException('Gönderici adı geçersiz.');
        foreach (['site_url', 'seo_social_image', 'seo_logo_url'] as $urlKey) {
            if ($values[$urlKey] === '') continue;
            $parts = parse_url($values[$urlKey]);
            if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) throw new RuntimeException('Geçerli mutlak HTTP(S) adresi girin: ' . $urlKey);
        }
        if ($values['search_console_verification'] !== '' && !preg_match('/^[A-Za-z0-9_-]{1,200}$/', $values['search_console_verification'])) throw new RuntimeException('Search Console doğrulama kodu geçersiz.');
        if ($values['smtp_password'] === '') $values['smtp_password'] = (string)($this->all()['smtp_password'] ?? '');
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO core_settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value'
            : 'INSERT INTO core_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=CURRENT_TIMESTAMP';
        $stmt = $this->db->prepare($sql);
        foreach ($values as $key => $value) $stmt->execute([$key, $value]);
    }
}
