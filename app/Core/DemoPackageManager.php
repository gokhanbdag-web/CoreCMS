<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;
use Throwable;

final class DemoPackageManager
{
    public function __construct(private PDO $db, private string $moduleDirectory) {}

    private function modules(): array
    {
        return (new ModuleManager($this->db, $this->moduleDirectory))->discover();
    }

    public function packages(string $moduleId): array
    {
        $modules = $this->modules();
        if (!isset($modules[$moduleId])) return [];
        $raw = $modules[$moduleId]['demo_packages'] ?? [];
        if (!is_array($raw)) return [];
        $packages = [];
        foreach ($raw as $package) {
            if (!is_array($package)) continue;
            $id = (string)($package['id'] ?? '');
            $name = trim((string)($package['name'] ?? ''));
            $description = trim((string)($package['description'] ?? ''));
            $pages = $package['pages'] ?? [];
            $menus = $package['menus'] ?? [];
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,39}$/', $id)
                || $name === '' || (preg_match_all('/./us', $name) ?: 0) > 120
                || !is_array($pages) || !is_array($menus)) continue;

            $validPages = [];
            foreach ($pages as $key => $page) {
                if (!is_string($key) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,49}$/', $key) || !is_array($page)) continue;
                $title = trim((string)($page['title'] ?? ''));
                $slug = trim((string)($page['slug'] ?? ''));
                $body = (string)($page['body'] ?? '');
                $status = (string)($page['status'] ?? 'published');
                if ($title === '' || (preg_match_all('/./us', $title) ?: 0) > 255
                    || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)
                    || strlen($body) > 1000000 || !in_array($status, ['draft','published'], true)
                    || (preg_match_all('/./us', (string)($page['seo_title'] ?? '')) ?: 0) > 255
                    || (preg_match_all('/./us', (string)($page['seo_description'] ?? '')) ?: 0) > 320) continue;
                $validPages[$key] = [
                    'title'=>$title,'slug'=>$slug,'body'=>$body,'status'=>$status,
                    'seo_title'=>trim((string)($page['seo_title'] ?? '')),
                    'seo_description'=>trim((string)($page['seo_description'] ?? '')),
                ];
            }

            $validMenus = [];
            foreach ($menus as $key => $menu) {
                if (!is_string($key) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,49}$/', $key) || !is_array($menu)) continue;
                $label = trim((string)($menu['label'] ?? ''));
                $url = trim((string)($menu['url'] ?? ''));
                $menuKey = trim((string)($menu['menu_key'] ?? 'main'));
                if ($label === '' || (preg_match_all('/./us', $label) ?: 0) > 190 || strlen($url) > 500
                    || $url === '' || !preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $menuKey)
                    || (str_starts_with($url, '/') ? str_starts_with($url, '//') : !preg_match('#^https://[^/\\s]+(?:/[^\\s]*)?$#i', $url))) continue;
                $validMenus[$key] = [
                    'label'=>$label,'url'=>$url,'menu_key'=>$menuKey,
                    'sort_order'=>(int)($menu['sort_order'] ?? 0),
                ];
            }

            $packages[] = [
                'id'=>$id,'name'=>$name,'description'=>$description,
                'pages'=>$validPages,'menus'=>$validMenus,
                'page_count'=>count($validPages),'menu_count'=>count($validMenus),
            ];
        }
        return $packages;
    }

    private function package(string $moduleId, string $demoId): array
    {
        foreach ($this->packages($moduleId) as $package) {
            if ($package['id'] === $demoId) return $package;
        }
        throw new RuntimeException('Demo paketi bulunamadı veya manifest verileri geçersiz.');
    }

    private function ensureTables(): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->db->exec('CREATE TABLE IF NOT EXISTS core_demo_installs (
                module_id TEXT NOT NULL, demo_id TEXT NOT NULL,
                installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (module_id,demo_id)
            )');
            $this->db->exec('CREATE TABLE IF NOT EXISTS core_module_content_registry (
                module_id TEXT NOT NULL, content_type TEXT NOT NULL, content_key TEXT NOT NULL,
                content_id INTEGER NOT NULL, managed_hash TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (module_id,content_type,content_key)
            )');
            $columns = $this->db->query('PRAGMA table_info(core_module_content_registry)')->fetchAll(PDO::FETCH_ASSOC);
            $hasHash = false;
            foreach ($columns as $column) if (($column['name'] ?? '') === 'managed_hash') $hasHash = true;
            if (!$hasHash) $this->db->exec('ALTER TABLE core_module_content_registry ADD COLUMN managed_hash TEXT NULL');
            return;
        }
        $this->db->exec("CREATE TABLE IF NOT EXISTS core_demo_installs (
            module_id VARCHAR(100) NOT NULL,
            demo_id VARCHAR(40) NOT NULL,
            installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,demo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS core_module_content_registry (
            module_id VARCHAR(100) NOT NULL, content_type VARCHAR(20) NOT NULL,
            content_key VARCHAR(100) NOT NULL, content_id BIGINT UNSIGNED NOT NULL,
            managed_hash CHAR(64) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,content_type,content_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $check = $this->db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $check->execute(['core_module_content_registry','managed_hash']);
        if ((int)$check->fetchColumn() === 0) $this->db->exec('ALTER TABLE core_module_content_registry ADD COLUMN managed_hash CHAR(64) NULL');
        $check = $this->db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $check->execute(['core_module_content_registry','managed_hash']);
        if ((int)$check->fetchColumn() === 0) $this->db->exec('ALTER TABLE core_module_content_registry ADD COLUMN managed_hash CHAR(64) NULL');
    }

    private function pageHash(array $page): string
    {
        $values = [
            'title'=>(string)$page['title'],'slug'=>(string)$page['slug'],
            'body'=>(string)($page['body'] ?? ''),'status'=>(string)$page['status'],
            'seo_title'=>(string)($page['seo_title'] ?? ''),
            'seo_description'=>(string)($page['seo_description'] ?? ''),
            'canonical_url'=>(string)($page['canonical_url'] ?? ''),
            'scheduled_at'=>(string)($page['scheduled_at'] ?? ''),
            'social_image_media_id'=>isset($page['social_image_media_id'])?(int)$page['social_image_media_id']:null,
            'source_module'=>(string)($page['source_module'] ?? ''),
            'source_demo'=>(string)($page['source_demo'] ?? ''),
        ];
        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));
    }

    private function menuHash(array $menu): string
    {
        $values = [
            'menu_key'=>(string)$menu['menu_key'],'label'=>(string)$menu['label'],
            'url'=>(string)$menu['url'],'sort_order'=>(int)$menu['sort_order'],
            'source_module'=>(string)($menu['source_module'] ?? ''),
            'source_demo'=>(string)($menu['source_demo'] ?? ''),
        ];
        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));
    }

    private function tableExists(string $table): bool
    {
        if (!in_array($table, ['core_page_revisions','core_page_autosaves'], true)) return false;
        if ((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt=$this->db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        } else {
            $stmt=$this->db->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        }
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private function hasHistory(int $pageId): bool
    {
        foreach (['core_page_revisions','core_page_autosaves'] as $table) {
            if (!$this->tableExists($table)) continue;
            $stmt=$this->db->prepare('SELECT 1 FROM '.$table.' WHERE page_id=? LIMIT 1');
            $stmt->execute([$pageId]);
            if ($stmt->fetchColumn()) return true;
        }
        return false;
    }

    private function prefix(string $demoId): string { return 'demo-'.$demoId.'-'; }

    private function registeredId(string $moduleId, string $type, string $key): int
    {
        $stmt=$this->db->prepare('SELECT content_id FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
        $stmt->execute([$moduleId,$type,$key]);
        return (int)$stmt->fetchColumn();
    }

    private function contentExists(string $type, int $id): bool
    {
        $table=$type==='page'?'core_pages':($type==='menu'?'core_menus':'');
        if ($table==='') return true;
        $stmt=$this->db->prepare('SELECT 1 FROM '.$table.' WHERE id=?');
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    private function register(string $moduleId,string $type,string $key,int $id,string $hash): void
    {
        $stmt=$this->db->prepare('INSERT INTO core_module_content_registry (module_id,content_type,content_key,content_id,managed_hash) VALUES (?,?,?,?,?)');
        $stmt->execute([$moduleId,$type,$key,$id,$hash]);
    }

    private function installContent(string $moduleId, string $demoId, array $package): array
    {
        $counts=['created_pages'=>0,'created_menus'=>0];
        $prefix=$this->prefix($demoId);
        foreach ($package['pages'] as $key=>$page) {
            $contentKey=$prefix.$key;
            if (strlen($contentKey)>100) throw new RuntimeException('Demo sayfa anahtarı çok uzun: '.$key);
            $registered=$this->registeredId($moduleId,'page',$contentKey);
            if ($registered>0 && $this->contentExists('page',$registered)) continue;
            if ($registered>0) {
                $del=$this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                $del->execute([$moduleId,'page',$contentKey]);
            }
            $stmt=$this->db->prepare('INSERT INTO core_pages (title,slug,body,status,seo_title,seo_description,source_module,source_demo) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$page['title'],$page['slug'],htmlspecialchars($page['body'],ENT_QUOTES,'UTF-8'),$page['status'],$page['seo_title'],$page['seo_description'],$moduleId,$demoId]);
            $id=(int)$this->db->lastInsertId();
            $row=$this->db->prepare('SELECT title,slug,body,status,seo_title,seo_description,canonical_url,scheduled_at,social_image_media_id,source_module,source_demo FROM core_pages WHERE id=?');
            $row->execute([$id]);
            $this->register($moduleId,'page',$contentKey,$id,$this->pageHash($row->fetch(PDO::FETCH_ASSOC) ?: []));
            $counts['created_pages']++;
        }
        foreach ($package['menus'] as $key=>$menu) {
            $contentKey=$prefix.$key;
            if (strlen($contentKey)>100) throw new RuntimeException('Demo menü anahtarı çok uzun: '.$key);
            $registered=$this->registeredId($moduleId,'menu',$contentKey);
            if ($registered>0 && $this->contentExists('menu',$registered)) continue;
            if ($registered>0) {
                $del=$this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                $del->execute([$moduleId,'menu',$contentKey]);
            }
            $stmt=$this->db->prepare('INSERT INTO core_menus (menu_key,label,url,sort_order,source_module,source_demo) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$menu['menu_key'],$menu['label'],$menu['url'],$menu['sort_order'],$moduleId,$demoId]);
            $id=(int)$this->db->lastInsertId();
            $row=$this->db->prepare('SELECT menu_key,label,url,sort_order,source_module,source_demo FROM core_menus WHERE id=?');
            $row->execute([$id]);
            $this->register($moduleId,'menu',$contentKey,$id,$this->menuHash($row->fetch(PDO::FETCH_ASSOC) ?: []));
            $counts['created_menus']++;
        }
        return $counts;
    }

    private function removeContent(string $moduleId, string $demoId): array
    {
        $result=['deleted_pages'=>0,'preserved_pages'=>0,'deleted_menus'=>0,'preserved_menus'=>0];
        $prefix=$this->prefix($demoId);
        $stmt=$this->db->prepare('SELECT content_type,content_key,content_id,managed_hash FROM core_module_content_registry WHERE module_id=? AND substr(content_key,1,?)=?');
        $stmt->execute([$moduleId,strlen($prefix),$prefix]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry) {
            $type=(string)$entry['content_type']; $key=(string)$entry['content_key']; $id=(int)$entry['content_id'];
            $hash=is_string($entry['managed_hash'] ?? null)?$entry['managed_hash']:'';
            if ($type==='page') {
                $q=$this->db->prepare('SELECT id,title,slug,body,status,seo_title,seo_description,canonical_url,scheduled_at,social_image_media_id,source_module,source_demo FROM core_pages WHERE id=?');
                $q->execute([$id]); $page=$q->fetch(PDO::FETCH_ASSOC);
                if (!$page) {
                    $d=$this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                    $d->execute([$moduleId,$type,$key]); continue;
                }
                $unchanged=$hash!=='' && hash_equals($hash,$this->pageHash($page)) && !$this->hasHistory($id)
                    && (string)($page['source_module']??'')===$moduleId && (string)($page['source_demo']??'')===$demoId;
                if ($unchanged) {
                    $d=$this->db->prepare('DELETE FROM core_pages WHERE id=?'); $d->execute([$id]);
                    $d=$this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                    $d->execute([$moduleId,$type,$key]); $result['deleted_pages']++;
                } else {
                    if ((string)($page['source_module']??'')===$moduleId && (string)($page['source_demo']??'')===$demoId) {
                        $d=$this->db->prepare('UPDATE core_pages SET source_module=NULL,source_demo=NULL WHERE id=?'); $d->execute([$id]);
                    }
                    $d=$this->db->prepare('UPDATE core_module_content_registry SET managed_hash=NULL WHERE module_id=? AND content_type=? AND content_key=?');
                    $d->execute([$moduleId,$type,$key]); $result['preserved_pages']++;
                }
            } elseif ($type==='menu') {
                $q=$this->db->prepare('SELECT id,menu_key,label,url,sort_order,source_module,source_demo FROM core_menus WHERE id=?');
                $q->execute([$id]); $menu=$q->fetch(PDO::FETCH_ASSOC);
                if (!$menu) {
                    $d=$this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                    $d->execute([$moduleId,$type,$key]); continue;
                }
                $unchanged=$hash!=='' && hash_equals($hash,$this->menuHash($menu))
                    && (string)($menu['source_module']??'')===$moduleId && (string)($menu['source_demo']??'')===$demoId;
                if ($unchanged) {
                    $d=$this->db->prepare('DELETE FROM core_menus WHERE id=?'); $d->execute([$id]);
                    $d=$this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                    $d->execute([$moduleId,$type,$key]); $result['deleted_menus']++;
                } else {
                    if ((string)($menu['source_module']??'')===$moduleId && (string)($menu['source_demo']??'')===$demoId) {
                        $d=$this->db->prepare('UPDATE core_menus SET source_module=NULL,source_demo=NULL WHERE id=?'); $d->execute([$id]);
                    }
                    $d=$this->db->prepare('UPDATE core_module_content_registry SET managed_hash=NULL WHERE module_id=? AND content_type=? AND content_key=?');
                    $d->execute([$moduleId,$type,$key]); $result['preserved_menus']++;
                }
            } else {
                $d=$this->db->prepare('UPDATE core_module_content_registry SET managed_hash=NULL WHERE module_id=? AND content_type=? AND content_key=?');
                $d->execute([$moduleId,$type,$key]);
            }
        }
        $stmt=$this->db->prepare('DELETE FROM core_demo_installs WHERE module_id=? AND demo_id=?');
        $stmt->execute([$moduleId,$demoId]);
        return $result;
    }

    private function starterPortfolio(string $operation,string $moduleId,string $demoId): void
    {
        if ($moduleId!=='real-estate' || $demoId!=='starter') return;
        require_once $this->moduleDirectory.'/real-estate/StarterPortfolio.php';
        if ($operation==='install') \\CoreCMS\\Modules\\RealEstate\\StarterPortfolio::install($this->db);
        else \\CoreCMS\\Modules\\RealEstate\\StarterPortfolio::remove($this->db);
    }

    public function preview(string $moduleId, string $demoId): array
    {
        $package=$this->package($moduleId,$demoId);
        $this->ensureTables();
        $stmt=$this->db->prepare('SELECT 1 FROM core_demo_installs WHERE module_id=? AND demo_id=?');
        $stmt->execute([$moduleId,$demoId]);
        $package['installed']=(bool)$stmt->fetchColumn();
        $package['module_installed']=(new ModuleManager($this->db,$this->moduleDirectory))->status($moduleId)['installed'];
        $package['module_enabled']=(new ModuleManager($this->db,$this->moduleDirectory))->status($moduleId)['enabled'];
        return $package;
    }

    public function install(string $moduleId, string $demoId): array
    {
        $package=$this->package($moduleId,$demoId);
        $status=(new ModuleManager($this->db,$this->moduleDirectory))->status($moduleId);
        if (!$status['installed'] || !$status['enabled']) throw new RuntimeException('Demo kurmak için önce modülü kurup etkinleştirin.');
        $this->ensureTables();
        $stmt=$this->db->prepare('SELECT 1 FROM core_demo_installs WHERE module_id=? AND demo_id=?');
        $stmt->execute([$moduleId,$demoId]);
        if ($stmt->fetchColumn()) return ['already_installed'=>true,'created_pages'=>0,'created_menus'=>0];
        $this->db->beginTransaction();
        try {
            $counts=$this->installContent($moduleId,$demoId,$package);
            $this->starterPortfolio('install',$moduleId,$demoId);
            $stmt=$this->db->prepare('INSERT INTO core_demo_installs (module_id,demo_id) VALUES (?,?)');
            $stmt->execute([$moduleId,$demoId]);
            $this->db->commit();
            return ['already_installed'=>false]+$counts;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw new RuntimeException('Demo paketi kurulamadı: '.$e->getMessage(),0,$e);
        }
    }

    public function reset(string $moduleId, string $demoId): array
    {
        $package=$this->package($moduleId,$demoId);
        $status=(new ModuleManager($this->db,$this->moduleDirectory))->status($moduleId);
        if (!$status['installed'] || !$status['enabled']) throw new RuntimeException('Demo sıfırlamak için modül kurulu ve etkin olmalıdır.');
        $this->ensureTables();
        $stmt=$this->db->prepare('SELECT 1 FROM core_demo_installs WHERE module_id=? AND demo_id=?');
        $stmt->execute([$moduleId,$demoId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Önce demo paketini kurun.');
        $this->db->beginTransaction();
        try {
            $this->starterPortfolio('remove',$moduleId,$demoId);
            $removed=$this->removeContent($moduleId,$demoId);
            $created=$this->installContent($moduleId,$demoId,$package);
            $this->starterPortfolio('install',$moduleId,$demoId);
            $stmt=$this->db->prepare('INSERT INTO core_demo_installs (module_id,demo_id) VALUES (?,?)');
            $stmt->execute([$moduleId,$demoId]);
            $this->db->commit();
            return $removed+$created;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw new RuntimeException('Demo paketi sıfırlanamadı: '.$e->getMessage(),0,$e);
        }
    }

    public function remove(string $moduleId, string $demoId): array
    {
        $this->package($moduleId,$demoId);
        $this->ensureTables();
        $stmt=$this->db->prepare('SELECT 1 FROM core_demo_installs WHERE module_id=? AND demo_id=?');
        $stmt->execute([$moduleId,$demoId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Demo paketi kurulu değil.');
        $this->db->beginTransaction();
        try {
            $this->starterPortfolio('remove',$moduleId,$demoId);
            $result=$this->removeContent($moduleId,$demoId);
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw new RuntimeException('Demo paketi kaldırılamadı: '.$e->getMessage(),0,$e);
        }
    }
}
