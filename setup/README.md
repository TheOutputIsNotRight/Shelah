# Shelah — Setup Guide

## 1. Create a Neon Database

1. Go to [https://neon.tech](https://neon.tech) and sign up / sign in.
2. Create a new project (e.g. "shelah").
3. Copy the **connection string** from the dashboard. It looks like:
   ```
   postgresql://username:password@ep-some-name.region.aws.neon.tech/dbname?sslmode=require
   ```

## 2. Set Environment Variables in Vercel

1. Go to your Vercel project → **Settings** → **Environment Variables**.
2. Add these variables:

   | Name                  | Value                                      |
   |-----------------------|--------------------------------------------|
   | `DATABASE_URL`        | Your Neon connection string (from step 1)   |
   | `GOOGLE_MAPS_API_KEY` | Your Google Maps API key (see step 3)       |

3. Make sure they apply to **Production**, **Preview**, and **Development**.

## 3. Get a Google Maps API Key

1. Go to [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project or select an existing one.
3. Go to **APIs & Services** → **Library**.
4. Enable these APIs:
   - **Maps JavaScript API**
   - **Geocoding API**
5. Go to **APIs & Services** → **Credentials**.
6. Click **Create Credentials** → **API Key**.
7. Restrict the key:
   - **Application restrictions**: HTTP referrers → add your Vercel domain(s).
   - **API restrictions**: Restrict to Maps JavaScript API and Geocoding API.
8. Copy the key and add it to Vercel env vars.

## 4. Deploy to Vercel

```bash
# Install Vercel CLI (if not already)
npm i -g vercel

# Deploy
vercel --prod
```

Or connect your GitHub repo to Vercel for automatic deployments.

## 5. Run the Seed Script

After deploying, visit this URL **once**:

```
https://your-app.vercel.app/setup/seed.php
```

This will:
1. Create all database tables
2. Insert 15 location types
3. Insert 17 sample Cairo places with realistic data

**⚠️ After running the seed script, delete the `setup/` directory from your repo for security.**

## 6. You're Done!

Visit your app at `https://your-app.vercel.app` and start using Shelah! 🎉
