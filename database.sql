
CREATE DATABASE sahlnaha
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE sahlnaha;
SET NAMES utf8mb4;

/* ================= USERS ================= */

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(30),
  password VARCHAR(255) NOT NULL,
  role ENUM('client','craftsman','admin') NOT NULL DEFAULT 'client',
  avatar VARCHAR(255),
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/* ================= GOVERNORATES ================= */

CREATE TABLE governorates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  lat DECIMAL(9,6),
  lng DECIMAL(9,6)
);

/* ================= AREAS ================= */

CREATE TABLE areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  governorate_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  FOREIGN KEY (governorate_id)
    REFERENCES governorates(id) ON DELETE CASCADE
);

/* ================= CATEGORIES ================= */

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  icon VARCHAR(40) NOT NULL,
  color VARCHAR(9) NOT NULL
);

/* ================= SERVICES ================= */

CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  keywords VARCHAR(255),
  FOREIGN KEY (category_id)
    REFERENCES categories(id) ON DELETE CASCADE
);

/* ================= CRAFTSMEN ================= */

CREATE TABLE craftsmen (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  category_id INT NOT NULL,
  governorate_id INT NOT NULL,
  area_id INT NULL,
  bio TEXT,
  experience_years TINYINT NOT NULL DEFAULT 0,
  price_from INT NOT NULL DEFAULT 0,
  available_days TINYINT NOT NULL DEFAULT 0,
  verified ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none',
  id_doc VARCHAR(255),
  cert_doc VARCHAR(255),
  lat DECIMAL(9,6),
  lng DECIMAL(9,6),
  views INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (governorate_id) REFERENCES governorates(id)
);

/* ================= CRAFTSMAN SERVICES ================= */

CREATE TABLE craftsman_services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  craftsman_id INT NOT NULL,
  service_id INT NOT NULL,
  price INT NOT NULL DEFAULT 0,
  views INT NOT NULL DEFAULT 0,
  UNIQUE (craftsman_id, service_id),
  FOREIGN KEY (craftsman_id)
    REFERENCES craftsmen(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id)
    REFERENCES services(id) ON DELETE CASCADE
);

/* ================= REVIEWS ================= */

CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  craftsman_id INT NOT NULL,
  client_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT,
  status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (craftsman_id, client_id),
  FOREIGN KEY (craftsman_id)
    REFERENCES craftsmen(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id)
    REFERENCES users(id) ON DELETE CASCADE
);

/* ================= REQUESTS ================= */

CREATE TABLE requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  category_id INT NOT NULL,
  service_id INT NULL,
  governorate_id INT NOT NULL,
  area_id INT NULL,
  address VARCHAR(255),
  description TEXT NOT NULL,
  budget INT NULL,
  urgency ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

/* ================= REQUEST TARGETS ================= */

CREATE TABLE request_targets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  craftsman_id INT NOT NULL,
  status ENUM('new','accepted','declined') NOT NULL DEFAULT 'new',
  UNIQUE (request_id, craftsman_id),
  FOREIGN KEY (request_id)
    REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (craftsman_id)
    REFERENCES craftsmen(id) ON DELETE CASCADE
);

/* ================= MESSAGES ================= */

CREATE TABLE messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  body TEXT NOT NULL,
  is_read TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (sender_id, receiver_id),
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

/* ================= FAVORITES ================= */

CREATE TABLE favorites (
  user_id INT NOT NULL,
  craftsman_id INT NOT NULL,
  PRIMARY KEY (user_id, craftsman_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (craftsman_id) REFERENCES craftsmen(id) ON DELETE CASCADE
);

/* ================= COMPLAINTS ================= */

CREATE TABLE complaints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  craftsman_id INT NOT NULL,
  user_id INT NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (craftsman_id)
    REFERENCES craftsmen(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* ================= PROFILE VIEWS ================= */

CREATE TABLE profile_views (
  id INT AUTO_INCREMENT PRIMARY KEY,
  craftsman_id INT NOT NULL,
  viewer_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (craftsman_id, created_at),
  FOREIGN KEY (craftsman_id)
    REFERENCES craftsmen(id) ON DELETE CASCADE
);

/* ================= NOTIFICATIONS ================= */

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  text VARCHAR(255) NOT NULL,
  link VARCHAR(255),
  is_read TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* ================= CONTACT MESSAGES ================= */

CREATE TABLE contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120),
  email VARCHAR(160),
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/* ================= GOVERNORATES DATA ================= */

INSERT INTO governorates (name, lat, lng) VALUES
('القاهرة',30.0444,31.2357),
('الجيزة',30.0131,31.2089),
('الإسكندرية',31.2001,29.9187),
('القليوبية',30.4100,31.2000),
('الشرقية',30.5900,31.5000),
('الدقهلية',31.0400,31.3800),
('الغربية',30.7900,31.0000),
('المنوفية',30.5500,31.0000),
('البحيرة',31.0400,30.4700),
('كفر الشيخ',31.1100,30.9400),
('دمياط',31.4200,31.8100),
('بورسعيد',31.2600,32.3000),
('الإسماعيلية',30.5900,32.2700),
('السويس',29.9668,32.5498),
('شمال سيناء',31.1300,33.8000),
('جنوب سيناء',28.2400,33.6200),
('الفيوم',29.3100,30.8400),
('بني سويف',29.0700,31.1000),
('المنيا',28.1100,30.7500),
('أسيوط',27.1800,31.1800),
('سوهاج',26.5600,31.6900),
('قنا',26.1600,32.7200),
('الأقصر',25.6900,32.6400),
('أسوان',24.0900,32.9000),
('البحر الأحمر',27.2600,33.8100),
('الوادي الجديد',25.4500,30.5500),
('مطروح',31.3500,27.2400);

/* ================= AREAS DATA ================= */

INSERT INTO areas (governorate_id, name) VALUES
(14,'الأربعين'),
(14,'فيصل'),
(14,'الجناين'),
(14,'العرب'),
(14,'عتاقة'),
(1,'مدينة نصر'),
(1,'المعادي'),
(1,'حلوان'),
(1,'التجمع الخامس'),
(2,'فيصل'),
(2,'الهرم'),
(2,'الدقي'),
(2,'6 أكتوبر'),
(3,'سموحة'),
(3,'المنتزه'),
(3,'سيدي جابر'),
(3,'العجمي');

/* ================= CATEGORIES DATA ================= */

INSERT INTO categories (name, icon, color) VALUES
('سباكة','faucet','#2a6fd6'),
('كهرباء','bolt','#f5b301'),
('نجارة','hammer','#e8891a'),
('دهانات','paint-roller','#7c5cd6'),
('تكييفات','snowflake','#1e9be8'),
('حدادة','screwdriver-wrench','#4a5fd0'),
('ألوميتال','table-cells-large','#3b4fc4'),
('بناء وتشطيبات','trowel-bricks','#e5533d'),
('أعمال منزلية','house-chimney','#14b08a'),
('أخرى','ellipsis','#6b7a99');

/* ================= SERVICES DATA ================= */

INSERT INTO services (category_id, name, keywords) VALUES
(1,'إصلاح تسريب مياه','تسريب,مياه,ماسورة,نقط'),
(1,'تركيب وصيانة سخان','سخان,مياه ساخنة'),
(1,'تسليك مجاري','انسداد,بالوعة,مجاري,طفح'),
(2,'إصلاح انقطاع الكهرباء','فصل,قطع,كهرباء,تماس'),
(2,'تركيب إنارة وثريات','لمبات,ثريا,إنارة'),
(2,'تأسيس كهرباء','تأسيس,أسلاك,لوحة'),
(3,'تفصيل مطبخ خشب','مطبخ,خشب'),
(3,'إصلاح أبواب وشبابيك','باب,شباك,قفل'),
(3,'تركيب دولاب غرف نوم','دولاب,غرفة نوم'),
(4,'دهان شقة كاملة','دهان,بوية,شقة'),
(4,'دهانات ديكور','ديكور,ورق حائط'),
(4,'معالجة الرطوبة','رطوبة,عفن,تقشير'),
(5,'تركيب تكييف','تكييف,تركيب,سبليت'),
(5,'صيانة وتنظيف تكييف','تنظيف,صيانة,تكييف'),
(5,'شحن فريون','فريون,تبريد,ضعيف'),
(6,'أبواب وبوابات حديد','باب حديد,بوابة'),
(6,'شبابيك وحمايات','حماية,شباك'),
(6,'أعمال لحام','لحام,تقطيع'),
(7,'شبابيك ألوميتال','شباك,ألوميتال'),
(7,'مطابخ ألوميتال','مطبخ,ألوميتال'),
(7,'واجهات زجاج','زجاج,واجهة'),
(8,'بياض ومحارة','محارة,بياض'),
(8,'تركيب سيراميك وبورسلين','سيراميك,بورسلين,أرضيات'),
(8,'تشطيب كامل','تشطيب,تجديد'),
(9,'تنظيف منازل','تنظيف,نظافة'),
(9,'نقل أثاث','نقل,عفش'),
(9,'مكافحة حشرات','حشرات,رش'),
(10,'تركيب دش وستالايت','دش,ستالايت,قنوات'),
(10,'كاميرات مراقبة','كاميرات,مراقبة'),
(10,'خدمة أخرى','أخرى');

/* ================= USERS DATA ================= */

/* Password for test accounts: password */

SET @pw = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

/* Admin password: j123456789 */

INSERT INTO users
(id,name,email,phone,password,role,avatar)
VALUES
(1,'مدير المنصة','jannamahfooz@gmail.com','01113459581',
'$2y$12$xRFWdfAjogUfSXelpNYYMeGqrh7zeCScRPECrVqFJj4MIXyYjSYMm',
'admin',NULL),

(2,'عميل تجريبي','client@sahlnaha.com','01000000002',@pw,'client',NULL),

(3,'أحمد محمود','ahmed@sahlnaha.com','01000000003',@pw,'craftsman','assets/img/workers/w1.jpg'),

(4,'محمد علي','mohamed@sahlnaha.com','01000000004',@pw,'craftsman','assets/img/workers/w2.jpg'),

(5,'خالد سعيد','khaled@sahlnaha.com','01000000005',@pw,'craftsman','assets/img/workers/w3.jpg'),

(6,'ياسر مصطفى','yasser@sahlnaha.com','01000000006',@pw,'craftsman','assets/img/workers/w4.jpg'),

(7,'عمرو أحمد','amr@sahlnaha.com','01000000007',@pw,'craftsman','assets/img/workers/w5.jpg'),

(8,'محمود حسن','mahmoud@sahlnaha.com','01000000008',@pw,'craftsman','assets/img/workers/w6.jpg'),

(9,'سارة إبراهيم','sara@sahlnaha.com','01000000009',@pw,'client',NULL),

(10,'منى حسن','mona@sahlnaha.com','01000000010',@pw,'client',NULL);

/* ================= CRAFTSMEN DATA ================= */

INSERT INTO craftsmen
(user_id,category_id,governorate_id,area_id,bio,experience_years,price_from,available_days,verified,lat,lng,views,created_at)
VALUES

(3,1,14,1,'سباك محترف بخبرة طويلة في تأسيس وصيانة شبكات المياه والصرف، شغل نضيف وأسعار واضحة.',8,200,0,'verified',29.9750,32.5400,320,NOW()-INTERVAL 200 DAY),

(4,2,1,6,'كهربائي معتمد لتأسيس وصيانة الكهرباء المنزلية والتجارية مع ضمان على الشغل.',5,250,0,'verified',30.0600,31.3300,210,NOW()-INTERVAL 150 DAY),

(5,3,2,10,'نجار متخصص في تفصيل المطابخ والدواليب والأبواب بخامات ممتازة وتسليم في الميعاد.',10,180,0,'verified',30.0100,31.2000,190,NOW()-INTERVAL 300 DAY),

(6,4,3,14,'فني دهانات وديكور، بنشتغل بأحدث الخامات ونعالج مشاكل الرطوبة.',4,150,3,'none',31.2100,29.9400,90,NOW()-INTERVAL 5 DAY),

(7,5,14,3,'فني تكييفات: تركيب وصيانة وشحن فريون لكل الأنواع.',6,300,1,'verified',29.9700,32.5500,143,NOW()-INTERVAL 100 DAY),

(8,8,14,2,'مقاول بناء وتشطيبات، تشطيب كامل للشقق والفلل بجودة عالية.',8,400,2,'verified',29.9600,32.5200,58,NOW()-INTERVAL 120 DAY);

/* ================= CRAFTSMAN SERVICES DATA ================= */

INSERT INTO craftsman_services (craftsman_id, service_id, price)
SELECT c.id, s.id, c.price_from + (s.id % 3) * 50
FROM craftsmen c
JOIN services s ON s.category_id = c.category_id;

/* ================= REVIEWS DATA ================= */

INSERT INTO reviews (craftsman_id, client_id, rating, comment)
SELECT
  c.id,
  u.id,
  4 + ((c.id + u.id) % 2),
  'شغل ممتاز وملتزم بالمواعيد، أنصح بالتعامل معه.'
FROM craftsmen c
JOIN users u ON u.role = 'client';