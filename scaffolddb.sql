-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3307
-- Généré le : dim. 02 nov. 2025 à 16:31
-- Version du serveur : 11.3.2-MariaDB
-- Version de PHP : 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `scaffolddb`
--

-- --------------------------------------------------------

--
-- Structure de la table `about_section`
--

DROP TABLE IF EXISTS `about_section`;
CREATE TABLE IF NOT EXISTS `about_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne descriptive au dessus du titre',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre principal de la section about',
  `image_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image associée (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_about_img` (`image_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Section A propos du site';

-- --------------------------------------------------------

--
-- Structure de la table `about_tabs`
--

DROP TABLE IF EXISTS `about_tabs`;
CREATE TABLE IF NOT EXISTS `about_tabs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de l''onglet',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> about_section.id',
  `tab_key` varchar(50) NOT NULL COMMENT 'Clé unique dans la section (ex: mission)',
  `tab_label` varchar(100) NOT NULL COMMENT 'Libellé visible de l''onglet',
  `metric_value` int(11) DEFAULT NULL COMMENT 'Valeur métrique affichée dans l''onglet (optionnel)',
  `metric_label` varchar(150) DEFAULT NULL COMMENT 'Libellé du métrique',
  `description` text DEFAULT NULL COMMENT 'Description détaillée pour l''onglet',
  `cta_label` varchar(100) DEFAULT NULL COMMENT 'Label CTA interne',
  `cta_url` varchar(512) DEFAULT NULL COMMENT 'URL du CTA',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_about_tab` (`section_id`,`tab_key`),
  KEY `idx_abouttab_order` (`section_id`,`published`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Onglets et contenu de la section about_section';

-- --------------------------------------------------------

--
-- Structure de la table `about_tab_bullets`
--

DROP TABLE IF EXISTS `about_tab_bullets`;
CREATE TABLE IF NOT EXISTS `about_tab_bullets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de la puce',
  `tab_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> about_tabs.id',
  `text` varchar(255) NOT NULL COMMENT 'Texte de la puce',
  `icon_class` varchar(100) DEFAULT 'far fa-check-square' COMMENT 'Classe icône par défaut',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`),
  KEY `idx_aboutbullet_order` (`tab_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Puces listées sous un onglet about_tab';

-- --------------------------------------------------------

--
-- Structure de la table `authors`
--

DROP TABLE IF EXISTS `authors`;
CREATE TABLE IF NOT EXISTS `authors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de l''auteur',
  `name` varchar(150) NOT NULL COMMENT 'Nom affiché de l''auteur',
  `avatar_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Photo de profil (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `idx_authors_name` (`name`),
  KEY `fk_authors_avatar_media` (`avatar_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auteurs des posts/articles';

-- --------------------------------------------------------

--
-- Structure de la table `company_profile`
--

DROP TABLE IF EXISTS `company_profile`;
CREATE TABLE IF NOT EXISTS `company_profile` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `company_name` varchar(255) DEFAULT NULL COMMENT 'Nom complet de l''entreprise',
  `address_line` varchar(255) DEFAULT NULL COMMENT 'Adresse physique principale',
  `email` varchar(255) DEFAULT NULL COMMENT 'Courriel de contact principal',
  `phone` varchar(50) DEFAULT NULL COMMENT 'Numéro de téléphone principal',
  `office_hours` varchar(255) DEFAULT NULL COMMENT 'Horaires d''ouverture',
  `logo_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK vers media.id pour le logo',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Date de création du profil',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Date de dernière modification',
  PRIMARY KEY (`id`),
  KEY `fk_company_logo` (`logo_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Informations d''entreprise affichées globalement';

--
-- Déchargement des données de la table `company_profile`
--

INSERT INTO `company_profile` (`id`, `company_name`, `address_line`, `email`, `phone`, `office_hours`, `logo_media_id`, `created_at`, `updated_at`) VALUES
(1, 'CongoleseYouth sarl', '25, Cyws, Gombe, Kinshasa', 'info@congoleseyouth.cd', '+243814864186', '08:00 - 18h00', 1, '2025-08-28 23:40:16', '2025-08-30 23:05:15');

-- --------------------------------------------------------

--
-- Structure de la table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du message',
  `name` varchar(150) NOT NULL COMMENT 'Nom de l''expéditeur',
  `email` varchar(255) NOT NULL COMMENT 'Email de l''expéditeur',
  `subject` varchar(255) DEFAULT NULL COMMENT 'Sujet du message',
  `message` text NOT NULL COMMENT 'Contenu du message',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Date de réception',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Messages soumis par les visiteurs';

-- --------------------------------------------------------

--
-- Structure de la table `contact_methods`
--

DROP TABLE IF EXISTS `contact_methods`;
CREATE TABLE IF NOT EXISTS `contact_methods` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de méthode',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> contact_section.id',
  `label` varchar(100) NOT NULL COMMENT 'Libellé (ex: Téléphone)',
  `value` varchar(255) NOT NULL COMMENT 'Valeur (ex: +243... ou email)',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe icône CSS',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`),
  KEY `idx_contactmethod_order` (`section_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Méthodes de contact affichées dans la section contact';

-- --------------------------------------------------------

--
-- Structure de la table `contact_section`
--

DROP TABLE IF EXISTS `contact_section`;
CREATE TABLE IF NOT EXISTS `contact_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne de présentation',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre principal de la section contact',
  `subtitle` varchar(255) DEFAULT NULL COMMENT 'Sous-titre pour informations additionnelles',
  `bg_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image de fond (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_contact_bg` (`bg_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contenu et configuration de la zone contact';

-- --------------------------------------------------------

--
-- Structure de la table `counters`
--

DROP TABLE IF EXISTS `counters`;
CREATE TABLE IF NOT EXISTS `counters` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du compteur',
  `label` varchar(255) NOT NULL COMMENT 'Libellé du compteur (ex: Projets finis)',
  `value` int(11) NOT NULL COMMENT 'Valeur numérique du compteur',
  `suffix` varchar(10) DEFAULT NULL COMMENT 'Suffixe facultatif (ex: +, K)',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> counter_section.id',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe d''icône CSS',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_counters_published_order` (`published`,`order_index`),
  KEY `fk_counter_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comptoirs statistiques présentés dans counter_section';

-- --------------------------------------------------------

--
-- Structure de la table `counter_section`
--

DROP TABLE IF EXISTS `counter_section`;
CREATE TABLE IF NOT EXISTS `counter_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `bg_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image de fond optionnelle (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_counter_bg` (`bg_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuration générale pour la section counters';

-- --------------------------------------------------------

--
-- Structure de la table `feature_items`
--

DROP TABLE IF EXISTS `feature_items`;
CREATE TABLE IF NOT EXISTS `feature_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de l''item',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> feature_section.id',
  `title` varchar(150) NOT NULL COMMENT 'Titre de l''élément',
  `description` text DEFAULT NULL COMMENT 'Description détaillée',
  `icon_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Icône sous forme de média (FK -> media.id)',
  `is_active` tinyint(1) DEFAULT 0 COMMENT 'Met en avant l''item si =1',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_featureitem_order` (`section_id`,`published`,`order_index`),
  KEY `fk_featureitem_icon` (`icon_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items affichés dans la section feature_section';

-- --------------------------------------------------------

--
-- Structure de la table `feature_section`
--

DROP TABLE IF EXISTS `feature_section`;
CREATE TABLE IF NOT EXISTS `feature_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne descriptive',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre de la section features',
  `cta_label` varchar(100) DEFAULT NULL COMMENT 'Label du bouton d''appel à l''action',
  `cta_url` varchar(512) DEFAULT NULL COMMENT 'URL du CTA',
  `bg_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image de fond (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_feature_bg` (`bg_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuration de la section features (zone services/USP)';

-- --------------------------------------------------------

--
-- Structure de la table `footer_columns`
--

DROP TABLE IF EXISTS `footer_columns`;
CREATE TABLE IF NOT EXISTS `footer_columns` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de colonne de footer',
  `title` varchar(100) DEFAULT NULL COMMENT 'Titre de la colonne (ex: Ressources)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Colonnes structurelles du pied de page';

-- --------------------------------------------------------

--
-- Structure de la table `footer_links`
--

DROP TABLE IF EXISTS `footer_links`;
CREATE TABLE IF NOT EXISTS `footer_links` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du lien',
  `column_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> footer_columns.id',
  `label` varchar(150) NOT NULL COMMENT 'Texte du lien',
  `url` varchar(512) NOT NULL COMMENT 'URL du lien',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage dans la colonne',
  PRIMARY KEY (`id`),
  KEY `idx_footerlink_order` (`column_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liens sous chaque colonne du footer';

-- --------------------------------------------------------

--
-- Structure de la table `hero_slides`
--

DROP TABLE IF EXISTS `hero_slides`;
CREATE TABLE IF NOT EXISTS `hero_slides` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du slide',
  `eyebrow` varchar(255) DEFAULT NULL COMMENT 'Petite ligne au dessus du titre',
  `title` varchar(255) NOT NULL COMMENT 'Titre du slide',
  `cta_label` varchar(100) DEFAULT NULL COMMENT 'Texte du bouton CTA',
  `cta_url` varchar(512) DEFAULT NULL COMMENT 'URL du CTA',
  `background_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image d''arrière plan (FK -> media.id)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_hero_published` (`published`,`order_index`),
  KEY `fk_hero_bg` (`background_media_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slides de la section hero';

--
-- Déchargement des données de la table `hero_slides`
--

INSERT INTO `hero_slides` (`id`, `eyebrow`, `title`, `cta_label`, `cta_url`, `background_media_id`, `order_index`, `published`) VALUES
(1, 'Empower your business', 'Déployez plus vite avec une plateforme fiable', 'Commencer', '/contact', 1, 0, 1),
(2, 'Bienvenue sur CongoleseYouth', 'Votre partenaire innovant en technologique numérique et digital.', 'S\'engager avec nous', '/contacts/contact.php', 2, 1, 1),
(3, 'Work without borders', 'Collaborez efficacement, où que soient vos équipes', 'En savoir plus', '/about', 3, 2, 1),
(4, 'Scale with confidence', 'Montez en échelle sans complexité ni interruptions', 'Découvrir', '/platform', 4, 3, 1),
(5, 'Go green', 'Accélérez votre transition vers une croissance durable', 'Études de cas', '/case-studies/green', 5, 4, 1);

-- --------------------------------------------------------

--
-- Structure de la table `media`
--

DROP TABLE IF EXISTS `media`;
CREATE TABLE IF NOT EXISTS `media` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant unique du média',
  `path` varchar(512) NOT NULL COMMENT 'Chemin relatif vers le fichier média',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre du média pour l''administration',
  `alt_text` varchar(255) DEFAULT NULL COMMENT 'Texte alternatif pour accessibilité/SEO',
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'Type MIME du fichier (image/png, image/jpeg, etc.)',
  `width` int(11) DEFAULT NULL COMMENT 'Largeur en pixels, si fournie',
  `height` int(11) DEFAULT NULL COMMENT 'Hauteur en pixels, si fournie',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Date de création du média',
  `media_type` varchar(50) NOT NULL COMMENT 'Catégorie ou usage du média (logo, slide, general, etc.)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_path` (`path`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bibliothèque de médias partagée pour les sections et items';

--
-- Déchargement des données de la table `media`
--

INSERT INTO `media` (`id`, `path`, `title`, `alt_text`, `mime_type`, `width`, `height`, `created_at`, `media_type`) VALUES
(1, 'assets/img/congoleseyouth_logo.png', 'Cyws Logo', 'Logo-CYWS', 'image/jpeg', 640, 640, '2025-08-28 23:55:54', 'logo'),
(2, 'assets/img/slides/welcome_slide.png', 'CongoleseYouth présentation', 'Congolese Youth iDigital.', 'image/png', 1920, 1280, '2025-08-29 10:00:00', 'slide'),
(3, 'assets/img/slides/welcome_slide_2.png', 'Équipe en collaboration', 'Équipe multiculturelle collaborant autour d’une table avec laptops', 'image/png', 1920, 1080, '2025-08-29 10:05:00', 'slide'),
(4, 'assets/img/slides/welcome_slide_3.png', 'Tableau de bord analytique', 'Grand écran affichant des graphiques et KPIs en temps réel', 'image/png', 2400, 1350, '2025-08-29 10:10:00', 'slide'),
(5, 'assets/img/slides/welcome_slide_4.png', 'Infrastructure cloud', 'Allée de serveurs dans un datacenter avec éclairage bleu', 'image/png', 1920, 1080, '2025-08-29 10:15:00', 'general'),
(6, 'assets/img/hero/sustainability.webp', 'Innovation durable', 'Panneaux solaires et éoliennes sous un ciel dégagé', 'image/webp', 2048, 1152, '2025-08-29 10:20:00', 'spécifique');

-- --------------------------------------------------------

--
-- Structure de la table `menus`
--

DROP TABLE IF EXISTS `menus`;
CREATE TABLE IF NOT EXISTS `menus` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du menu',
  `key` varchar(50) NOT NULL COMMENT 'Identifiant logique du menu (ex: main)',
  `title` varchar(100) DEFAULT NULL COMMENT 'Titre administratif du menu',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_menus_key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Menus du site (ensemble d''items)';

--
-- Déchargement des données de la table `menus`
--

INSERT INTO `menus` (`id`, `key`, `title`) VALUES
(1, 'main', 'Bienvenue sur la page d\'accueil');

-- --------------------------------------------------------

--
-- Structure de la table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de l''item',
  `menu_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> menus.id',
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Self-FK vers menu_items.id pour structure arborescente',
  `label` varchar(255) NOT NULL COMMENT 'Label affiché',
  `url` varchar(512) DEFAULT NULL COMMENT 'URL du lien',
  `page_slug` varchar(255) DEFAULT NULL COMMENT 'Slug interne de page (optionnel)',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe d''icône CSS',
  `target` varchar(20) DEFAULT NULL COMMENT 'Cible du lien (_self, _blank)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `enabled` tinyint(1) DEFAULT 1 COMMENT 'Activé ou non',
  PRIMARY KEY (`id`),
  KEY `idx_menuitems_menu_order` (`menu_id`,`order_index`),
  KEY `fk_menuitems_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items navigations, appartenant à un menu';

--
-- Déchargement des données de la table `menu_items`
--

INSERT INTO `menu_items` (`id`, `menu_id`, `parent_id`, `label`, `url`, `page_slug`, `icon_class`, `target`, `order_index`, `enabled`) VALUES
(1, 1, NULL, 'accueil', './', 'home', NULL, '_self', 0, 1),
(2, 1, NULL, 'services', './views/pages/services.php', 'services', NULL, '_self', 0, 1),
(3, 1, NULL, 'projets', './views/pages/projects.php', 'projets', NULL, '_self', 0, 1),
(4, 1, NULL, 'contactez-nous', './views/pages/contact.php', 'contacts', NULL, '_self', 0, 1);

-- --------------------------------------------------------

--
-- Structure de la table `newsletter_subscribers`
--

DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment',
  `email` varchar(255) NOT NULL COMMENT 'Email de l''abonné',
  `status` enum('active','unsubscribed','bounced') NOT NULL DEFAULT 'active' COMMENT 'Statut de l''abonnement',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date d''inscription',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liste des abonnés à la newsletter';

-- --------------------------------------------------------

--
-- Structure de la table `posts`
--

DROP TABLE IF EXISTS `posts`;
CREATE TABLE IF NOT EXISTS `posts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du post',
  `title` varchar(255) NOT NULL COMMENT 'Titre du post',
  `slug` varchar(200) NOT NULL COMMENT 'Slug unique pour URL',
  `excerpt` text DEFAULT NULL COMMENT 'Résumé court',
  `body` mediumtext DEFAULT NULL COMMENT 'Contenu HTML/Markdown du post',
  `featured_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image à la une (FK -> media.id)',
  `author_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Auteur (FK -> authors.id)',
  `published_at` datetime DEFAULT NULL COMMENT 'Date de publication',
  `status` enum('draft','published','scheduled') NOT NULL DEFAULT 'draft' COMMENT 'Statut editorial',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_slug` (`slug`),
  KEY `idx_posts_status_published` (`status`,`published_at`),
  KEY `idx_posts_author` (`author_id`),
  KEY `fk_posts_featured_media` (`featured_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Articles et actualités du site (remplace news_articles)';

-- --------------------------------------------------------

--
-- Structure de la table `post_categories`
--

DROP TABLE IF EXISTS `post_categories`;
CREATE TABLE IF NOT EXISTS `post_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de catégorie',
  `name` varchar(150) NOT NULL COMMENT 'Nom visible de la catégorie',
  `slug` varchar(150) NOT NULL COMMENT 'Slug unique de la catégorie',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Taxonomie catégorie pour posts';

-- --------------------------------------------------------

--
-- Structure de la table `post_category_post`
--

DROP TABLE IF EXISTS `post_category_post`;
CREATE TABLE IF NOT EXISTS `post_category_post` (
  `post_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> posts.id',
  `category_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> post_categories.id',
  PRIMARY KEY (`post_id`,`category_id`),
  KEY `idx_post_category_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pivot posts <-> categories';

-- --------------------------------------------------------

--
-- Structure de la table `post_tags`
--

DROP TABLE IF EXISTS `post_tags`;
CREATE TABLE IF NOT EXISTS `post_tags` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du tag',
  `name` varchar(150) NOT NULL COMMENT 'Nom du tag',
  `slug` varchar(150) NOT NULL COMMENT 'Slug unique du tag',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Taxonomie tag pour posts';

-- --------------------------------------------------------

--
-- Structure de la table `post_tag_post`
--

DROP TABLE IF EXISTS `post_tag_post`;
CREATE TABLE IF NOT EXISTS `post_tag_post` (
  `post_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> posts.id',
  `tag_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> post_tags.id',
  PRIMARY KEY (`post_id`,`tag_id`),
  KEY `idx_post_tag_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pivot posts <-> tags';

-- --------------------------------------------------------

--
-- Structure de la table `services`
--

DROP TABLE IF EXISTS `services`;
CREATE TABLE IF NOT EXISTS `services` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du service',
  `name` varchar(150) NOT NULL COMMENT 'Nom du service',
  `slug` varchar(150) DEFAULT NULL COMMENT 'Slug optionnel pour page service',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe d''icône CSS',
  `excerpt` text DEFAULT NULL COMMENT 'Texte court de présentation',
  `body` mediumtext DEFAULT NULL COMMENT 'Description complète',
  `details_url` varchar(512) DEFAULT NULL COMMENT 'URL externe ou interne de détails',
  `number_badge` varchar(10) DEFAULT NULL COMMENT 'Badge numérique court (ex: 01)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Visibilité',
  PRIMARY KEY (`id`),
  KEY `idx_services_order` (`published`,`order_index`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catalogue des services fournis';

--
-- Déchargement des données de la table `services`
--

INSERT INTO `services` (`id`, `name`, `slug`, `icon_class`, `excerpt`, `body`, `details_url`, `number_badge`, `order_index`, `published`) VALUES
(1, 'Modélisation Esthétique', NULL, 'fas fa-mobile-alt', 'Dans le bon', NULL, NULL, NULL, 0, 1),
(2, 'Formation en Informatique Générale', NULL, 'fas fa-info-alt', 'Une formation professionnelle de qualité adaptée au marché de l\'emploi.', NULL, NULL, NULL, 1, 1),
(3, 'Support Informatique', 'support-informatique', 'fas fa-headset', 'Assistance technique rapide et efficace pour vos besoins informatiques quotidiens.', 'Notre service de support informatique vous accompagne dans la résolution\r\nde vos problèmes techniques, la maintenance de vos systèmes et la\r\nformation de vos équipes. Nous intervenons sur Windows, Linux et\r\nenvironnements réseaux pour garantir la continuité de vos activités.', 'http://localhost:8000/services/support-informatique', '01', 1, 1),
(4, 'Maintenance Réseau', 'maintenance-reseau', 'fas fa-network-wired', 'Supervision, dépannage et optimisation de vos infrastructures réseau.', '', 'http://localhost:8000//services/maintenance-reseau', '', 0, 1),
(5, 'Sécurité Systèmes', 'securite-systemes', 'fas fa-shield-alt', 'Protection avancée contre les menaces et conformité aux normes de sécurité.', '', 'http://localhost:8000/services/securite-systemes', '', 0, 1),
(6, 'Gestion des Bases de Données', 'gestion-bases-donnees', 'fas fa-database', '', '', 'http://localhost:8000//services/gestion-bases-donnees', '04', 0, 1),
(7, 'Virtualisation & Cloud', 'virtualisation-cloud', 'fas fa-cloud', '', '', 'http://localhost:8000/services/virtualisation-cloud', '05', 0, 1),
(8, 'Modélisation Esthétique', 'modelisation-esthetique', 'fas fa-pencil-ruler', 'Dans le bon', NULL, '#', '01', 0, 1),
(9, 'Formation en Informatique', 'formation-informatique', 'fas fa-info-circle', 'Une formation professionnelle de qualité adaptée au marché de l\'emploi.', NULL, '#', '02', 1, 1),
(10, 'Développement Web', 'developpement-web', 'fas fa-code', 'Création de sites web modernes et performants pour une présence en ligne optimale.', NULL, '#', '03', 2, 1),
(11, 'Marketing Digital', 'marketing-digital', 'fas fa-bullhorn', 'Stratégies de marketing numérique pour accroître votre visibilité et votre engagement.', '', '#', '04', 3, 1),
(12, 'Banque Digitale Sécurisée', 'anque-igitale-ecuris-ee', 'fas fa-university', 'Plateforme bancaire en ligne fiable, rapide et sécurisée, accessible 24/7.', 'Gérez vos comptes et transactions en toute sécurité grâce à notre plateforme bancaire digitale.', 'http://localhost:8000/services/anque-igitale-ecuris-ee', '12', 0, 1);

-- --------------------------------------------------------

--
-- Structure de la table `service_section`
--

DROP TABLE IF EXISTS `service_section`;
CREATE TABLE IF NOT EXISTS `service_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne descriptive',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre principal de la section services',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuration générale pour la section services';

-- --------------------------------------------------------

--
-- Structure de la table `skills`
--

DROP TABLE IF EXISTS `skills`;
CREATE TABLE IF NOT EXISTS `skills` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment',
  `name` varchar(150) NOT NULL COMMENT 'Nom de la compétence',
  `percent` tinyint(3) UNSIGNED NOT NULL COMMENT 'Pourcentage de compétence (0-100)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_skills_published_order` (`published`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Compétences affichées (barres, etc.)';

-- --------------------------------------------------------

--
-- Structure de la table `social_links`
--

DROP TABLE IF EXISTS `social_links`;
CREATE TABLE IF NOT EXISTS `social_links` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du lien social',
  `platform` varchar(50) NOT NULL COMMENT 'Nom de la plateforme (facebook, twitter, etc.)',
  `url` varchar(512) NOT NULL COMMENT 'URL ou identifiant (selon usage)',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe icône',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `enabled` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platform` (`platform`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liens et identifiants des profils sociaux';

--
-- Déchargement des données de la table `social_links`
--

INSERT INTO `social_links` (`id`, `platform`, `url`, `icon_class`, `order_index`, `enabled`) VALUES
(3, 'facebook', 'congoleseyouth_sarl', 'fab fa-facebook-f', 1, 1),
(4, 'twitter', 'congolese_youth', 'fab fa-twitter', 2, 1),
(5, 'whatsapp', 'congoleseyouth', 'fab fa-whatsapp', 3, 1);

-- --------------------------------------------------------

--
-- Structure de la table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du témoignage',
  `author_name` varchar(150) NOT NULL COMMENT 'Nom de la personne',
  `author_role` varchar(150) DEFAULT NULL COMMENT 'Rôle ou titre de la personne',
  `content` text NOT NULL COMMENT 'Texte du témoignage',
  `photo_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Photo (FK -> media.id)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_testimonial_order` (`published`,`order_index`),
  KEY `fk_testimonial_photo` (`photo_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Témoignages clients';

-- --------------------------------------------------------

--
-- Structure de la table `testimonial_section`
--

DROP TABLE IF EXISTS `testimonial_section`;
CREATE TABLE IF NOT EXISTS `testimonial_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `bg_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image de fond (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_testimonial_section_bg_media` (`bg_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paramètres globaux pour la section témoignages';

-- --------------------------------------------------------

--
-- Structure de la table `trust_bullets`
--

DROP TABLE IF EXISTS `trust_bullets`;
CREATE TABLE IF NOT EXISTS `trust_bullets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de la puce',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> trust_section.id',
  `text` varchar(255) NOT NULL COMMENT 'Texte de la puce',
  `icon_class` varchar(100) DEFAULT 'fas fa-check-circle' COMMENT 'Classe icône',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`),
  KEY `idx_trustbullet_order` (`section_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Points de confiance affichés dans trust_section';

-- --------------------------------------------------------

--
-- Structure de la table `trust_section`
--

DROP TABLE IF EXISTS `trust_section`;
CREATE TABLE IF NOT EXISTS `trust_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne descriptive',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre de la section trust',
  `body` text DEFAULT NULL COMMENT 'Texte descriptif ou mission',
  `image_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image associée (FK -> media.id)',
  `video_url` varchar(512) DEFAULT NULL COMMENT 'URL vidéo optionnelle',
  `cta_label` varchar(100) DEFAULT NULL COMMENT 'Label CTA',
  `cta_url` varchar(512) DEFAULT NULL COMMENT 'URL du CTA',
  PRIMARY KEY (`id`),
  KEY `fk_trust_img` (`image_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Section confiance / why trust us';

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `about_section`
--
ALTER TABLE `about_section`
  ADD CONSTRAINT `fk_about_img` FOREIGN KEY (`image_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `about_tabs`
--
ALTER TABLE `about_tabs`
  ADD CONSTRAINT `fk_abouttab_section` FOREIGN KEY (`section_id`) REFERENCES `about_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `about_tab_bullets`
--
ALTER TABLE `about_tab_bullets`
  ADD CONSTRAINT `fk_aboutbullet_tab` FOREIGN KEY (`tab_id`) REFERENCES `about_tabs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `authors`
--
ALTER TABLE `authors`
  ADD CONSTRAINT `fk_authors_avatar_media` FOREIGN KEY (`avatar_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `company_profile`
--
ALTER TABLE `company_profile`
  ADD CONSTRAINT `fk_company_logo` FOREIGN KEY (`logo_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `contact_methods`
--
ALTER TABLE `contact_methods`
  ADD CONSTRAINT `fk_contactmethod_section` FOREIGN KEY (`section_id`) REFERENCES `contact_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `contact_section`
--
ALTER TABLE `contact_section`
  ADD CONSTRAINT `fk_contact_bg` FOREIGN KEY (`bg_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `counters`
--
ALTER TABLE `counters`
  ADD CONSTRAINT `fk_counter_section` FOREIGN KEY (`section_id`) REFERENCES `counter_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `counter_section`
--
ALTER TABLE `counter_section`
  ADD CONSTRAINT `fk_counter_bg` FOREIGN KEY (`bg_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `feature_items`
--
ALTER TABLE `feature_items`
  ADD CONSTRAINT `fk_featureitem_icon` FOREIGN KEY (`icon_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_featureitem_section` FOREIGN KEY (`section_id`) REFERENCES `feature_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `feature_section`
--
ALTER TABLE `feature_section`
  ADD CONSTRAINT `fk_feature_bg` FOREIGN KEY (`bg_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `footer_links`
--
ALTER TABLE `footer_links`
  ADD CONSTRAINT `fk_footerlink_column` FOREIGN KEY (`column_id`) REFERENCES `footer_columns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD CONSTRAINT `fk_hero_bg` FOREIGN KEY (`background_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `fk_menuitems_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_menuitems_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_posts_featured_media` FOREIGN KEY (`featured_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `post_category_post`
--
ALTER TABLE `post_category_post`
  ADD CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `post_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pc_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `post_tag_post`
--
ALTER TABLE `post_tag_post`
  ADD CONSTRAINT `fk_pt_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pt_tag` FOREIGN KEY (`tag_id`) REFERENCES `post_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `fk_testimonial_photo` FOREIGN KEY (`photo_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `testimonial_section`
--
ALTER TABLE `testimonial_section`
  ADD CONSTRAINT `fk_testimonial_section_bg_media` FOREIGN KEY (`bg_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `trust_bullets`
--
ALTER TABLE `trust_bullets`
  ADD CONSTRAINT `fk_trustbullet_section` FOREIGN KEY (`section_id`) REFERENCES `trust_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `trust_section`
--
ALTER TABLE `trust_section`
  ADD CONSTRAINT `fk_trust_img` FOREIGN KEY (`image_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
