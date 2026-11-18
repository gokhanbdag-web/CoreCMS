<?php
declare(strict_types=1);

return [
 'up' => static function (PDO $db): void {
  $sqlite=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
  if($sqlite){
   $db->exec("CREATE TABLE IF NOT EXISTS re_agents (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT NULL,phone TEXT NULL,title TEXT NULL,bio TEXT NULL,photo_url TEXT NULL,status TEXT NOT NULL DEFAULT 'active',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
   $db->exec("CREATE TABLE IF NOT EXISTS re_listings (id INTEGER PRIMARY KEY AUTOINCREMENT,agent_id INTEGER NULL,title TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,listing_type TEXT NOT NULL,property_type TEXT NOT NULL,price NUMERIC NOT NULL,currency TEXT NOT NULL DEFAULT 'TRY',city TEXT NOT NULL,district TEXT NOT NULL,neighborhood TEXT NULL,address TEXT NULL,bedrooms INTEGER NULL,bathrooms INTEGER NULL,area_gross INTEGER NULL,area_net INTEGER NULL,building_age INTEGER NULL,floor_number INTEGER NULL,total_floors INTEGER NULL,heating TEXT NULL,description TEXT NOT NULL,features_json TEXT NOT NULL DEFAULT '[]',cover_image TEXT NULL,gallery_json TEXT NOT NULL DEFAULT '[]',status TEXT NOT NULL DEFAULT 'draft',featured INTEGER NOT NULL DEFAULT 0,seo_title TEXT NULL,seo_description TEXT NULL,published_at TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(agent_id) REFERENCES re_agents(id) ON DELETE SET NULL)");
   $db->exec("CREATE INDEX IF NOT EXISTS idx_re_listings_search ON re_listings(status,listing_type,property_type,city,district,price)");
   $db->exec("CREATE TABLE IF NOT EXISTS re_leads (id INTEGER PRIMARY KEY AUTOINCREMENT,listing_id INTEGER NULL,name TEXT NOT NULL,email TEXT NOT NULL,phone TEXT NULL,message TEXT NOT NULL,source TEXT NOT NULL DEFAULT 'website',status TEXT NOT NULL DEFAULT 'new',ip_hash TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(listing_id) REFERENCES re_listings(id) ON DELETE SET NULL)");
   $db->exec("CREATE INDEX IF NOT EXISTS idx_re_leads_status ON re_leads(status,created_at)");
  }else{
   $db->exec("CREATE TABLE IF NOT EXISTS re_agents (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(160) NOT NULL,email VARCHAR(190) NULL,phone VARCHAR(60) NULL,title VARCHAR(160) NULL,bio TEXT NULL,photo_url VARCHAR(500) NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_re_agents_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
   $db->exec("CREATE TABLE IF NOT EXISTS re_listings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,agent_id BIGINT UNSIGNED NULL,title VARCHAR(255) NOT NULL,slug VARCHAR(190) NOT NULL UNIQUE,listing_type VARCHAR(20) NOT NULL,property_type VARCHAR(40) NOT NULL,price DECIMAL(15,2) NOT NULL,currency CHAR(3) NOT NULL DEFAULT 'TRY',city VARCHAR(100) NOT NULL,district VARCHAR(100) NOT NULL,neighborhood VARCHAR(120) NULL,address VARCHAR(500) NULL,bedrooms SMALLINT NULL,bathrooms SMALLINT NULL,area_gross INT NULL,area_net INT NULL,building_age SMALLINT NULL,floor_number SMALLINT NULL,total_floors SMALLINT NULL,heating VARCHAR(100) NULL,description LONGTEXT NOT NULL,features_json LONGTEXT NOT NULL,cover_image VARCHAR(500) NULL,gallery_json LONGTEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'draft',featured TINYINT(1) NOT NULL DEFAULT 0,seo_title VARCHAR(255) NULL,seo_description VARCHAR(320) NULL,published_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_re_listings_search(status,listing_type,property_type,city,district,price),CONSTRAINT fk_re_listing_agent FOREIGN KEY(agent_id) REFERENCES re_agents(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
   $db->exec("CREATE TABLE IF NOT EXISTS re_leads (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,listing_id BIGINT UNSIGNED NULL,name VARCHAR(160) NOT NULL,email VARCHAR(190) NOT NULL,phone VARCHAR(60) NULL,message TEXT NOT NULL,source VARCHAR(30) NOT NULL DEFAULT 'website',status VARCHAR(20) NOT NULL DEFAULT 'new',ip_hash CHAR(64) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_re_leads_status(status,created_at),CONSTRAINT fk_re_lead_listing FOREIGN KEY(listing_id) REFERENCES re_listings(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
 },
 'down' => static function (PDO $db): void {
  $db->exec('DROP TABLE IF EXISTS re_leads');
  $db->exec('DROP TABLE IF EXISTS re_listings');
  $db->exec('DROP TABLE IF EXISTS re_agents');
 }
];
