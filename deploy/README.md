# Deployment Guide

## 1) Prepare variables
```bash
cp deploy/scripts/deploy.env.example deploy/scripts/deploy.env
```
Edit `deploy/scripts/deploy.env` with your server/domain values.

## 2) Make scripts executable
```bash
chmod +x deploy/scripts/setup-server.sh deploy/scripts/deploy.sh
```

## 3) One-time server setup
```bash
./deploy/scripts/setup-server.sh
```
This will configure:
- Nginx virtual host
- Supervisor queue worker
- Laravel scheduler cron

## 4) Deploy app code
```bash
./deploy/scripts/deploy.sh
```

## 5) SSL (recommended)
```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

## Notes
- Ensure `.env` exists in `APP_DIR`.
- Ensure DB credentials are valid before migrations.
- `deploy.sh` can skip migrations with:
```bash
RUN_MIGRATIONS=0 ./deploy/scripts/deploy.sh
```
