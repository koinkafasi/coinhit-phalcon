# CoinHit - Phalcon Betting Prediction Platform

Modern web-based sports betting prediction platform built with Phalcon PHP framework.

## Features

- 🎯 Advanced prediction algorithms
- ⚽ Multi-sport support (Football, Basketball, Tennis, etc.)
- 📊 Real-time match data and statistics
- 🎫 Coupon system for managing predictions
- 👥 User management and authentication
- 🔐 JWT-based secure API
- 📈 Analytics and reporting
- 💎 Subscription plans

## Tech Stack

- **Backend**: Phalcon PHP 5.x
- **Database**: MySQL 8.0
- **Authentication**: JWT
- **API**: RESTful

## Installation

### Requirements

- PHP 8.1+
- MySQL 8.0+
- Composer
- Phalcon extension
- Nginx/Apache

### Setup

1. Clone the repository:
```bash
git clone https://github.com/koinkafasi/coinhit-phalcon.git
cd coinhit-phalcon
```

2. Install dependencies:
```bash
cd backend
composer install
```

3. Configure environment:
```bash
cp .env.example .env
# Edit .env with your database credentials
```

4. Run migrations:
```bash
vendor/bin/phinx migrate
```

5. Set permissions:
```bash
chmod -R 775 cache logs
chown -R www-data:www-data .
```

## Deployment

Use the provided deployment script:
```bash
./deploy.sh
```

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout

### Matches
- `GET /api/matches` - List matches
- `GET /api/matches/{id}` - Get match details

### Predictions
- `GET /api/predictions` - List predictions
- `POST /api/predictions` - Create prediction

### Coupons
- `GET /api/coupons` - List user coupons
- `POST /api/coupons` - Create coupon

## License

Proprietary - All rights reserved

## Contact

For support: support@coinhit.net
