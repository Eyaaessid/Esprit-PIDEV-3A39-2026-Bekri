# Bekri – Plateforme de Bien-Être Mental et Physique

## Overview
Ce projet a été développé dans le cadre du **PIDEV – Programme d'Ingénierie 3ème Année** à **Esprit School of Engineering** (Année Universitaire 2025–2026).

**Bekri** est une plateforme unifiée dédiée à la prévention et au suivi du bien-être mental et physique. Elle offre aux utilisateurs un espace centralisé pour évaluer leur état de santé, suivre leur progression et accéder à des ressources adaptées à leurs besoins.

---

## Features

- 🧠 **Évaluation initiale** – Diagnostic personnalisé de l'état mental et physique de l'utilisateur
- 📊 **Analyse hebdomadaire** – Rapports et synthèses hebdomadaires sur l'évolution du bien-être
- 📅 **Suivi quotidien** – Journal de bord pour un suivi régulier au jour le jour
- 🧪 **Gestion des tests mentaux** – Administration et résultats de tests psychologiques standardisés
- 🎯 **Gestion des événements** – Planification et suivi d'activités liées au bien-être
- 📝 **Gestion des posts** – Espace communautaire pour partager des expériences et conseils

---

## Tech Stack

### Frontend
- HTML5
- CSS3
- JavaScript

### Backend
- PHP
- Symfony Framework

### Base de données
- MySQL
- phpMyAdmin

---

## Architecture

L'application suit une architecture **MVC (Modèle-Vue-Contrôleur)** basée sur le framework Symfony, garantissant une séparation claire des responsabilités entre la logique métier, la présentation et la gestion des données.

---

## Contributors

| Nom | Rôle |
|-----|------|
| Eya Essid | Développement Full-Stack |
| Hiba Ibn Hadj Mohammed | Développement Full-Stack |
| Aziz Jedidi | Développement Full-Stack |
| Aziz Barhoumi | Développement Full-Stack |
| Adem Ben Amara | Développement Full-Stack |

---

## Academic Context

Développé à **Esprit School of Engineering – Tunisia**  
**PIDEV – 3A | 2025–2026**

---

## Getting Started

### Prérequis
- PHP >= 8.1
- Composer
- Symfony CLI
- Serveur MySQL (XAMPP / WAMP recommandé)

### Installation

```bash
# Cloner le dépôt
git clone https://github.com/Eyaaessid/Esprit-PIDEV-3A39-2026-Bekri.git

# Accéder au dossier
cd Esprit-PIDEV-3A39-2026-Bekri

# Installer les dépendances
composer install

# Configurer la base de données dans .env
DATABASE_URL="mysql://root:@127.0.0.1:3306/bekri_db?serverVersion=8.0.45&charset=utf8mb4"

# Créer la base de données et appliquer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Lancer le serveur
symfony server:start
```

---

## Acknowledgments

Nous remercions **Esprit School of Engineering** pour l'encadrement pédagogique, ainsi que nos tuteurs et encadrants pour leur soutien tout au long du développement de ce projet.
