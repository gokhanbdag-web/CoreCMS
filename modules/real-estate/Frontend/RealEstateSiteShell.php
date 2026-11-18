<?php
declare(strict_types=1);

namespace CoreCMS\Modules\RealEstate;

use PDO;

final class RealEstateSiteShell
{
    private array $settings;
    private array $menus;
    private string $siteName;
    private string $locale='tr';

    public function __construct(PDO $db)
    {
        require_once dirname(__DIR__,3).'/app/Core/SiteSettings.php';
        require_once dirname(__DIR__,3).'/app/Core/ContentManager.php';
        require_once dirname(__DIR__,3).'/app/Core/LocaleManager.php';

        $this->settings=(new \CoreCMS\Core\SiteSettings($db))->all();
        $this->siteName=trim((string)($this->settings['seo_org_name']??'')) ?: trim((string)($this->settings['site_name']??''));
        if($this->siteName==='')$this->siteName='Gayrimenkul Ofisi';
        $this->locale=(new \CoreCMS\Core\LocaleManager($db))->defaultCode();
        $this->menus=(new \CoreCMS\Core\ContentManager($db))->menusForLocale('main',$this->locale);
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }

    private function menuUrl(string $url): string
    {
        return preg_match('#^https://#i',$url) ? $url : \\CoreCMS\\Core\\Url::to($url);
    }

    public function header(string $title,string $description,string $base): string
    {
        $logo=trim((string)($this->settings['seo_logo_url']??''));
        $brand=$logo!==''?'<img src="'.$this->e($logo).'" alt="'.$this->e($this->siteName).'">':$this->e($this->siteName);
        $html='<!doctype html><html lang="'.$this->e($this->locale).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$this->e($title).'</title><meta name="description" content="'.$this->e($description).'"><link rel="stylesheet" href="'.\CoreCMS\Core\Url::to('/modules/real-estate/assets/css/real-estate.css').'"></head><body class="re-site"><header class="re-header"><a class="re-brand" href="'.$this->e($base).'">'.$brand.'</a><nav aria-label="Ana menü">';
        foreach($this->menus as $menu){
            $url=(string)($menu['url']??'');
            if($url==='')continue;
            $html.='<a href="'.$this->e(\CoreCMS\Core\Url::to($url)).'">'.$this->e($menu['label']??'').'</a>';
        }
        $html.='</nav></header><main class="re-container">';
        return $html;
    }

    public function footer(): string
    {
        return '</main><footer class="re-footer">'.$this->e($this->siteName).' · '.date('Y').'</footer></body></html>';
    }
}
