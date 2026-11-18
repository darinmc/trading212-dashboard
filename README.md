# Trading 212 Portfolio Dashboard

A Symfony 6.4 (LTS) web application for tracking and analysing investment portfolios via the Trading 212 API.

Built to consolidate data from multiple Trading 212 accounts into a single, customisable dashboard.

## The Problem

Trading 212 provides a mobile app for trading, but doesn't offer a unified, cross-account dashboard for deep portfolio analysis, historical tracking, or custom reporting. This project solves that by pulling data directly from the Trading 212 API, storing it locally, and presenting it in a way that suits my specific needs.

## Tech Stack

- **Framework:** Symfony 6.4 (LTS)
- **Language:** PHP 8.x
- **Database:** MySQL 8 (Doctrine ORM with versioned migrations)
- **Frontend:** Twig templating, vanilla JavaScript, CSS3
- **API Integration:** `marekskopal/trading212` client library + Guzzle/Symfony HttpClient
- **Authentication:** Symfony Security

## Features

- **API Integration:** Connects to Invest and ISA accounts via the Trading 212 API.
- **Scheduled Sync:** Custom Symfony Console commands designed to run via cron for nightly data synchronisation (positions, history, and prices).
- **Data Persistence:** Uses Doctrine ORM and migrations for structured, version-controlled database schema management.
- **Multi-Account Support:** Aggregates data across different account types.
- **Trade History & Analytics:** Tracks sold holdings, trade history, and current positions.
- **Custom Notes:** Allows adding notes to specific positions for future reference.

## Architecture

The application follows standard Symfony best practices:
- `src/Command/`: Console commands for data fetching and syncing.
- `src/Controller/`: Route handlers for the web interface.
- `src/Entity/`: Doctrine entities representing the database schema (Portfolio, Position, Transaction, User).
- `src/Service/`: Business logic, including the `Trading212Service` which wraps the API client.
- `src/Security/`: Custom authenticator for user login.
- `migrations/`: Version-controlled database schema changes.

## Environment Variables

To run this project, you need to provide the following environment variables (usually in a `.env.local` file, which is excluded from version control):

```env
# Database
DATABASE_URL="mysql://user:password@127.0.0.1:3306/dbname?serverVersion=8.0.32&charset=utf8mb4"

# App Environment
APP_ENV=dev
APP_SECRET=your_app_secret_here

# Trading 212 API Keys
T212_INVEST_API_KEY="your_invest_key_here"
T212_INVEST_API_SECRET="your_invest_secret_here"
T212_ISA_API_KEY="your_isa_key_here"
T212_ISA_API_SECRET="your_isa_secret_here"

# SIPP account placeholders (API access pending from Trading 212)
T212_SIPP_API_KEY=""
T212_SIPP_API_SECRET=""

