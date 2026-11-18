<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class RoleManager
{
    private const PERMISSIONS = [
        'dashboard.view' => 'Kontrol panelini görüntüleme',
        'pages.manage' => 'Sayfa oluşturma ve düzenleme',
        'menus.manage' => 'Menüleri yönetme',
        'media.manage' => 'Medya kütüphanesini yönetme',
        'seo.manage' => 'SEO ve yönlendirmeleri yönetme',
        'modules.manage' => 'Modülleri yönetme',
        'demos.manage' => 'Demo paketlerini yönetme',
        'appearance.manage' => 'Tema ve görünümü yönetme',
        'settings.manage' => 'Site ayarlarını yönetme',
        'users.manage' => 'Kullanıcı ve rolleri yönetme',
        'forms.manage' => 'Formları ve e-posta şablonlarını yönetme',
        'listings.view' => 'Emlak ilanlarını görüntüleme',
        'listings.manage' => 'Emlak ilanlarını yönetme',
        'agents.manage' => 'Emlak danışmanlarını yönetme',
        'leads.manage' => 'Emlak müşteri taleplerini yönetme',
    ];

    public function __construct(private PDO $db) {}

    public static function permissionCatalog(): array { return self::PERMISSIONS; }

    public function can(string $role, string $permission): bool
    {
        if ($role === 'super_admin' || $role === 'admin') return true;
        if (!isset(self::PERMISSIONS[$permission])) return false;
        $stmt = $this->db->prepare('SELECT 1 FROM core_role_permissions rp INNER JOIN core_roles r ON r.id=rp.role_id WHERE r.role_key=? AND rp.permission_key=? LIMIT 1');
        $stmt->execute([$role, $permission]);
        return (bool)$stmt->fetchColumn();
    }

    public function roleExists(string $role): bool
    {
        if (in_array($role, ['admin', 'super_admin'], true)) return true;
        $stmt = $this->db->prepare('SELECT 1 FROM core_roles WHERE role_key=? LIMIT 1');
        $stmt->execute([$role]);
        return (bool)$stmt->fetchColumn();
    }

    public function roles(): array
    {
        return $this->db->query('SELECT r.id,r.role_key,r.name,r.is_system,COUNT(rp.permission_key) permission_count FROM core_roles r LEFT JOIN core_role_permissions rp ON rp.role_id=r.id GROUP BY r.id,r.role_key,r.name,r.is_system ORDER BY r.is_system DESC,r.name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function permissionsForRole(string $role): array
    {
        $stmt=$this->db->prepare('SELECT rp.permission_key FROM core_role_permissions rp INNER JOIN core_roles r ON r.id=rp.role_id WHERE r.role_key=?');
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function save(?int $id, string $key, string $name, array $permissions): int
    {
        $key=strtolower(trim($key));
        $name=trim($name);
        if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/',$key) || in_array($key,['admin','super_admin'],true)) throw new RuntimeException('Rol anahtarı 2-40 karakter olmalı; harf, rakam ve alt çizgi kullanılabilir.');
        if ($name==='' || (preg_match_all('/./us', $name) ?: 0)>100) throw new RuntimeException('Rol adı zorunludur ve 100 karakteri aşamaz.');
        $allowed=array_keys(self::PERMISSIONS);
        $permissions=array_values(array_unique(array_filter($permissions,static fn($p)=>is_string($p)&&in_array($p,$allowed,true))));
        $this->db->beginTransaction();
        try {
            if ($id) {
                $stmt=$this->db->prepare('SELECT role_key,is_system FROM core_roles WHERE id=?');
                $stmt->execute([$id]); $existing=$stmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing || (int)$existing['is_system']===1) throw new RuntimeException('Sistem rolleri düzenlenemez.');
                if ($existing['role_key'] !== $key) {
                    $moveUsers=$this->db->prepare('UPDATE core_users SET role=? WHERE role=?');
                    $moveUsers->execute([$key,$existing['role_key']]);
                }
                $stmt=$this->db->prepare('UPDATE core_roles SET role_key=?,name=? WHERE id=?');
                $stmt->execute([$key,$name,$id]);
            } else {
                $stmt=$this->db->prepare('INSERT INTO core_roles (role_key,name,is_system) VALUES (?,?,0)');
                $stmt->execute([$key,$name]); $id=(int)$this->db->lastInsertId();
            }
            $stmt=$this->db->prepare('DELETE FROM core_role_permissions WHERE role_id=?'); $stmt->execute([$id]);
            $stmt=$this->db->prepare('INSERT INTO core_role_permissions (role_id,permission_key) VALUES (?,?)');
            foreach($permissions as $permission) $stmt->execute([$id,$permission]);
            $this->db->commit();
            return $id;
        } catch(\Throwable $e) {
            if($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $stmt=$this->db->prepare('SELECT role_key,is_system FROM core_roles WHERE id=?');
        $stmt->execute([$id]); $role=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$role || (int)$role['is_system']===1) throw new RuntimeException('Sistem rolleri silinemez.');
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM core_users WHERE role=?'); $stmt->execute([$role['role_key']]);
        if ((int)$stmt->fetchColumn()>0) throw new RuntimeException('Bu role atanmış kullanıcılar var. Önce kullanıcı rollerini değiştirin.');
        $stmt=$this->db->prepare('DELETE FROM core_roles WHERE id=?'); $stmt->execute([$id]);
    }

    public function assignUser(int $userId, string $role, bool $allowAdminPromotion = false): void
    {
        if ($role === 'admin' && !$allowAdminPromotion) throw new RuntimeException('Yönetici rolü yalnızca tam yetkili yöneticiler tarafından atanabilir.');
        if (!$this->roleExists($role)) throw new RuntimeException('Seçilen rol bulunamadı.');
        if ($role==='super_admin') throw new RuntimeException('Süper yönetici rolü bu ekrandan atanamaz.');
        $stmt=$this->db->prepare("UPDATE core_users SET role=? WHERE id=? AND role<>'super_admin'");
        $stmt->execute([$role,$userId]);
        if ($stmt->rowCount()===0) {
            $check=$this->db->prepare('SELECT role FROM core_users WHERE id=?'); $check->execute([$userId]);
            $current=$check->fetchColumn();
            if ($current===false) throw new RuntimeException('Kullanıcı bulunamadı.');
            if ($current==='super_admin') throw new RuntimeException('Süper yönetici hesabı değiştirilemez.');
        }
    }
}
