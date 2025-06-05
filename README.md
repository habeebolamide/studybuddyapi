
---

### ✅ `README.md` for **StudyBuddy Laravel Backend**

```markdown
# 🖥️ StudyBuddy – Laravel Backend

**StudyBuddy** is an AI-powered learning backend that supports PDF uploads, generates simplified summaries, and creates quizzes for students. This Laravel project provides secure APIs consumed by the Flutter mobile frontend.

---

## ✨ Features

- 🔐 User Authentication (JWT or Sanctum)
- 📄 File upload and PDF processing
- 🤖 AI integration for summarization and quiz creation
- 🧪 Quiz generation and evaluation
- 📡 RESTful API endpoints

---

## 🧰 Tech Stack

- Laravel 10+
- PHP 8.1+
- MySQL
- Laravel Sanctum (or Passport)
- OpenAI API
- CORS for mobile integration

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.1+
- Composer
- MySQL
- Laravel CLI

### Installation

```bash
git clone https://github.com/habeebolamide/studybuddyapi.git
cd studybuddyapi
composer install
cp .env.example .env
php artisan key:generate
